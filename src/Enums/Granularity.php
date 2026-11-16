<?php

declare(strict_types=1);

namespace Webstat\Analytics\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

enum Granularity: string
{
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    /** Bucket key format; must match what SqlDialect::bucket() produces. */
    public function keyFormat(): string
    {
        return match ($this) {
            self::Hour => 'Y-m-d H:00:00',
            self::Day, self::Week => 'Y-m-d',
            self::Month => 'Y-m-01',
        };
    }

    public function floor(CarbonInterface $d): CarbonImmutable
    {
        $d = CarbonImmutable::instance($d);

        return match ($this) {
            self::Hour => $d->startOfHour(),
            self::Day => $d->startOfDay(),
            self::Week => $d->startOfWeek(CarbonInterface::MONDAY)->startOfDay(),
            self::Month => $d->startOfMonth(),
        };
    }

    public function next(CarbonImmutable $d): CarbonImmutable
    {
        return match ($this) {
            self::Hour => $d->addHour(),
            self::Day => $d->addDay(),
            self::Week => $d->addWeek(),
            self::Month => $d->addMonth(),
        };
    }

    public function display(string $key): string
    {
        $c = CarbonImmutable::parse($key);

        return match ($this) {
            self::Hour => $c->format('H:00'),
            self::Day, self::Week => $c->translatedFormat('j M'),
            self::Month => $c->translatedFormat('M Y'),
        };
    }

    public function label(): string
    {
        return __('analytics::analytics.group.' . $this->value);
    }
}
