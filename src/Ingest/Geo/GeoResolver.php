<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest\Geo;

use Illuminate\Http\Request;

interface GeoResolver
{
    /**
     * Cheap, synchronous lookup (CDN headers, offline database).
     *
     * @param  string|null  $ip  real visitor IP (see ClientIp)
     * @return array{country: ?string, city: ?string}  ISO-3166 alpha-2 country, city name
     */
    public function resolve(Request $request, ?string $ip): array;
}
