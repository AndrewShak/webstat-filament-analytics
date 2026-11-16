<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest\Geo;

use Illuminate\Http\Request;

final class NullGeoResolver implements GeoResolver
{
    public function resolve(Request $request, ?string $ip): array
    {
        return ['country' => null, 'city' => null];
    }
}
