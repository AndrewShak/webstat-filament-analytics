<?php

declare(strict_types=1);

namespace Webstat\Analytics\Support;

final class Countries
{
    public static function name(string $code): string
    {
        $code = strtoupper($code);
        if (class_exists(\Locale::class)) {
            $n = \Locale::getDisplayRegion('-' . $code, app()->getLocale());
            if ($n && $n !== $code) {
                return $n;
            }
        }

        return $code;
    }

    public static function flag(string $code): string
    {
        $code = strtoupper($code);
        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return '';
        }

        return mb_chr(0x1F1E6 + ord($code[0]) - 65) . mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }
}
