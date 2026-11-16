<?php

declare(strict_types=1);

namespace Webstat\Analytics\Enums;

enum Audience: string
{
    case Humans = 'humans';
    case Bots = 'bots';
    case All = 'all';

    public function label(): string
    {
        return __('analytics::analytics.audience.' . $this->value);
    }
}
