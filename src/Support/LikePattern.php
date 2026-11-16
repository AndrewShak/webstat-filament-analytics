<?php

declare(strict_types=1);

namespace Webstat\Analytics\Support;

/** LIKE patterns using '!' as escape char (use with: ... like ? escape '!'). */
final class LikePattern
{
    public static function escape(string $s): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s);
    }

    public static function contains(string $s): string
    {
        return '%' . self::escape($s) . '%';
    }

    /** '*' acts as a wildcard, everything else is literal. */
    public static function wildcard(string $s): string
    {
        return implode('%', array_map(self::escape(...), explode('*', $s)));
    }
}
