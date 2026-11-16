<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest\Geo;

/** Online IP -> country/city lookup, used only after the response was sent (see Writer). */
interface IpLookup
{
    /** @return array{country: ?string, city: ?string} */
    public function lookup(string $ip): array;
}
