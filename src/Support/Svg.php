<?php

declare(strict_types=1);

namespace Webstat\Analytics\Support;

final class Svg
{
    public const PALETTE = [
        'var(--primary-500)', 'var(--info-500)', 'var(--success-500)',
        'var(--warning-500)', 'var(--danger-500)', 'var(--gray-400)',
    ];

    public static function niceMax(float|int $max): int
    {
        if ($max <= 4) {
            return 4;
        }
        $pow = 10 ** (int) floor(log10($max));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($m * $pow >= $max) {
                return (int) ceil($m * $pow);
            }
        }

        return (int) ceil($max);
    }

    public static function duration(int $seconds): string
    {
        return intdiv($seconds, 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }
}
