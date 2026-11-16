<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest\Geo;

use Illuminate\Http\Request;

final class ChainGeoResolver implements GeoResolver
{
    /** @param list<GeoResolver> $resolvers */
    public function __construct(private readonly array $resolvers) {}

    public function resolve(Request $request, ?string $ip = null): array
    {
        $out = ['country' => null, 'city' => null];
        foreach ($this->resolvers as $r) {
            $res = $r->resolve($request, $ip);
            $out['country'] ??= $res['country'];
            $out['city'] ??= $res['city'];
            if ($out['country'] && $out['city']) {
                break;
            }
        }

        return $out;
    }
}
