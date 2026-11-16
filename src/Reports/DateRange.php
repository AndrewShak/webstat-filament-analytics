<?php

declare(strict_types=1);

namespace Webstat\Analytics\Reports;

use Carbon\CarbonImmutable;
use Webstat\Analytics\Enums\Granularity;
use Webstat\Analytics\Enums\Preset;

final readonly class DateRange
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public Granularity $granularity,
    ) {}

    public static function make(Preset $preset, ?string $from = null, ?string $to = null, ?Granularity $group = null): self
    {
        $now = CarbonImmutable::now();

        [$f, $t] = match ($preset) {
            Preset::Day => [$now->startOfDay(), $now],
            Preset::Week => [$now->subDays(6)->startOfDay(), $now],
            Preset::Month => [$now->subDays(29)->startOfDay(), $now],
            Preset::Quarter => [$now->subDays(89)->startOfDay(), $now],
            Preset::Year => [$now->subDays(364)->startOfDay(), $now],
            Preset::Custom => self::custom($from, $to, $now),
        };

        return new self($f, $t, $group ?? self::auto($f, $t));
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private static function custom(?string $from, ?string $to, CarbonImmutable $now): array
    {
        try {
            $f = CarbonImmutable::parse((string) $from)->startOfDay();
            $t = CarbonImmutable::parse((string) $to)->endOfDay();
        } catch (\Throwable) {
            return [$now->subDays(29)->startOfDay(), $now];
        }
        if ($t->lt($f)) {
            [$f, $t] = [$t->startOfDay(), $f->endOfDay()];
        }
        if (abs($f->diffInYears($t)) > 5) {
            $f = $t->subYears(5);
        }

        return [$f, $t->gt($now) ? $now : $t];
    }

    public static function auto(CarbonImmutable $from, CarbonImmutable $to): Granularity
    {
        $days = abs($from->diffInDays($to));

        return match (true) {
            $days <= 2 => Granularity::Hour,
            $days <= 100 => Granularity::Day,
            $days <= 400 => Granularity::Week,
            default => Granularity::Month,
        };
    }

    public function previous(): self
    {
        $len = (int) abs($this->to->diffInSeconds($this->from));
        $prevTo = $this->from->subSecond();

        return new self($prevTo->subSeconds($len), $prevTo, $this->granularity);
    }

    /** @return list<string> */
    public function buckets(): array
    {
        $keys = [];
        $c = $this->granularity->floor($this->from);
        while ($c->lte($this->to) && count($keys) < 4000) {
            $keys[] = $c->format($this->granularity->keyFormat());
            $c = $this->granularity->next($c);
        }

        return $keys;
    }

    public function key(): string
    {
        return $this->from->format('YmdHi') . '-' . $this->to->format('YmdHi') . '-' . $this->granularity->value;
    }
}
