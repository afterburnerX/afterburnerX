<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Uploads a file to any S3-compatible object store (AWS S3, Cloudflare
 * R2, DigitalOcean Spaces, MinIO, ...) via a plain PUT signed with AWS
 * Signature Version 4 - no AWS SDK dependency, since this app is
 * otherwise dependency-free and the SDK is a lot of weight for "PUT one
 * object".
 */
class S3Uploader
{
    public static function upload(string $localPath, string $key, string $contentType): string
    {
        $endpoint = rtrim((string) env('S3_ENDPOINT'), '/');
        $region = (string) env('S3_REGION', 'us-east-1');
        $bucket = (string) env('S3_BUCKET');
        $accessKey = (string) env('S3_ACCESS_KEY_ID');
        $secretKey = (string) env('S3_SECRET_ACCESS_KEY');

        if ($endpoint === '' || $bucket === '' || $accessKey === '' || $secretKey === '') {
            throw new RuntimeException('S3 storage is not fully configured (S3_ENDPOINT/S3_BUCKET/S3_ACCESS_KEY_ID/S3_SECRET_ACCESS_KEY).');
        }

        $host = self::hostHeaderFor($endpoint);
        $usePathStyle = self::truthy(env('S3_PATH_STYLE'));

        $url = $usePathStyle
            ? "{$endpoint}/{$bucket}/{$key}"
            : preg_replace('#^(https?://)#', '$1' . $bucket . '.', $endpoint) . "/{$key}";
        $signingHost = $usePathStyle ? $host : "{$bucket}.{$host}";
        $canonicalUri = $usePathStyle ? "/{$bucket}/{$key}" : "/{$key}";

        $body = file_get_contents($localPath);
        if ($body === false) {
            throw new RuntimeException('Could not read file for S3 upload.');
        }

        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);

        $canonicalHeaders = "host:{$signingHost}\n"
            . "x-amz-acl:public-read\n"
            . "x-amz-content-sha256:{$payloadHash}\n"
            . "x-amz-date:{$amzDate}\n";
        $signedHeaders = 'host;x-amz-acl;x-amz-content-sha256;x-amz-date';

        $canonicalRequest = implode("\n", ['PUT', $canonicalUri, '', $canonicalHeaders, $signedHeaders, $payloadHash]);

        $credentialScope = "{$dateStamp}/{$region}/s3/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signature = hash_hmac('sha256', $stringToSign, self::signingKey($secretKey, $dateStamp, $region, 's3'));

        $authorization = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$credentialScope}, "
            . "SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Host: {$signingHost}",
            "x-amz-date: {$amzDate}",
            'x-amz-acl: public-read',
            "x-amz-content-sha256: {$payloadHash}",
            "Authorization: {$authorization}",
            "Content-Type: {$contentType}",
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('S3 upload failed: ' . $error);
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("S3 upload failed with HTTP {$httpCode}: " . $response);
        }

        $publicBase = env('S3_PUBLIC_URL_BASE');

        return $publicBase ? rtrim($publicBase, '/') . '/' . $key : $url;
    }

    /**
     * The Host header value for an endpoint, including the port when it
     * isn't the scheme default. SigV4 signs the Host header, so dropping
     * a non-default port (e.g. a MinIO endpoint on :9000) would sign a
     * host that doesn't match the connection and fail verification.
     */
    public static function hostHeaderFor(string $endpoint): string
    {
        $host = (string) parse_url($endpoint, PHP_URL_HOST);
        $port = parse_url($endpoint, PHP_URL_PORT);
        $scheme = strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));

        $isDefaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);

        if ($port === null || $isDefaultPort) {
            return $host;
        }

        return "{$host}:{$port}";
    }

    /**
     * AWS SigV4 signing key derivation. Public so it can be verified
     * against AWS's published test vectors without a network call.
     */
    public static function signingKey(string $secretKey, string $dateStamp, string $region, string $service): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    private static function truthy(?string $value): bool
    {
        return $value !== null && !in_array(strtolower($value), ['', '0', 'false', 'no'], true);
    }
}
