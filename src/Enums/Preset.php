<?php

declare(strict_types=1);

namespace Webstat\Analytics\Enums;

enum Preset: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';
    case Custom = 'custom';

    public function label(): string
    {
        return __('analytics::analytics.presets.' . $this->value);
    }
}
