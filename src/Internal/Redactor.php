<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Internal;

/**
 * Masks credentials in text that may end up in logs (exception messages,
 * problem details). The SDK never logs on its own.
 */
final class Redactor
{
    /** @codeCoverageIgnore Static helpers only. */
    private function __construct() {}

    public static function redact(string $text): string
    {
        $text = (string) preg_replace('/\bimproj_(pat|org)_[A-Za-z0-9]+/', 'improj_$1_***', $text);
        $text = (string) preg_replace('/\bwhsec_[A-Za-z0-9_-]+/', 'whsec_***', $text);
        $text = (string) preg_replace('/\bimp_[A-Za-z0-9]{20,}/', 'imp_***', $text);

        return (string) preg_replace('/(Bearer\s+)[^\s"\',;]+/i', '$1***', $text);
    }

    /** Shows only the kind prefix and the last 4 characters. */
    public static function maskToken(?string $token): string
    {
        if ($token === null || $token === '') {
            return '(none)';
        }
        $prefix = preg_match('/^(improj_(?:pat|org)_|imp_)/', $token, $m) === 1 ? $m[1] : '';

        return $prefix . '***' . (\strlen($token) > \strlen($prefix) + 8 ? substr($token, -4) : '');
    }
}
