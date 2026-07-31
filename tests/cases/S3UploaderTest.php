<?php

declare(strict_types=1);

use App\S3Uploader;

test('signingKey() matches an independent HMAC-SHA256 chain (Python hmac/hashlib) for the same inputs', function () {
    // AWS SigV4's example secret key from
    // https://docs.aws.amazon.com/general/latest/gr/sigv4-calculate-signature.html,
    // cross-checked against a from-scratch Python implementation of the
    // same kDate/kRegion/kService/kSigning derivation rather than a
    // hand-copied expected value.
    $secretKey = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';
    $key = S3Uploader::signingKey($secretKey, '20150830', 'us-east-1', 'iam');

    assertSame('2c94c0cf5378ada6887f09bb697df8fc0affdb34ba1cdd5bda32b664bd55b73c', bin2hex($key));
});

test('signingKey() produces a different key for a different date', function () {
    $secretKey = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';
    $a = S3Uploader::signingKey($secretKey, '20150830', 'us-east-1', 'iam');
    $b = S3Uploader::signingKey($secretKey, '20150831', 'us-east-1', 'iam');

    assertFalse($a === $b, 'Expected signing keys for different dates to differ');
});

test('hostHeaderFor() keeps a non-default port, which SigV4 signs', function () {
    // Regression: parse_url(..., PHP_URL_HOST) drops the port, so a MinIO
    // endpoint on :9000 would sign a Host that doesn't match the request.
    assertSame('minio.internal:9000', S3Uploader::hostHeaderFor('http://minio.internal:9000'));
    assertSame('127.0.0.1:9101', S3Uploader::hostHeaderFor('http://127.0.0.1:9101'));
    assertSame('s3.example.com:8443', S3Uploader::hostHeaderFor('https://s3.example.com:8443'));
});

test('hostHeaderFor() omits the port when it is the scheme default', function () {
    assertSame('s3.us-east-1.amazonaws.com', S3Uploader::hostHeaderFor('https://s3.us-east-1.amazonaws.com'));
    assertSame('s3.us-east-1.amazonaws.com', S3Uploader::hostHeaderFor('https://s3.us-east-1.amazonaws.com:443'));
    assertSame('minio.internal', S3Uploader::hostHeaderFor('http://minio.internal:80'));
});
