<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest\Geo;

final class NullIpLookup implements IpLookup
{
    public function lookup(string $ip): array
    {
        return ['country' => null, 'city' => null];
    }
}
