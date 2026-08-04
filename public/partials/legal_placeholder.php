<?php
/**
 * Renders a company/contact detail from .env, or a loud placeholder if
 * it hasn't been set. The placeholder is deliberately conspicuous so an
 * unfilled legal page can't quietly go live.
 */
function legal_value(string $envKey, string $label): string
{
    $value = env($envKey);

    if ($value === null || trim($value) === '') {
        return '<mark style="background:#ffdf9a;color:#3a331f;padding:0 4px;">['
            . htmlspecialchars($label) . ' — set ' . htmlspecialchars($envKey) . ' in .env]</mark>';
    }

    return htmlspecialchars($value);
}

function legal_is_configured(): bool
{
    foreach (['COMPANY_NAME', 'COMPANY_CONTACT_EMAIL', 'COMPANY_ADDRESS'] as $key) {
        $value = env($key);
        if ($value === null || trim($value) === '') {
            return false;
        }
    }

    return true;
}
