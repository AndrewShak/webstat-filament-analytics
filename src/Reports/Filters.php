<?php

declare(strict_types=1);

namespace Webstat\Analytics\Reports;

use Webstat\Analytics\Enums\Audience;

final readonly class Filters
{
    public function __construct(public DateRange $range, public Audience $audience = Audience::Humans) {}

    public function key(): string
    {
        return $this->range->key() . '-' . $this->audience->value;
    }
}
