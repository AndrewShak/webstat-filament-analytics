<?php

declare(strict_types=1);

namespace Webstat\Analytics\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Webstat\Analytics\Ingest\Ingestor;

final class CollectController
{
    public function __invoke(Request $request, Ingestor $ingestor): Response
    {
        $empty = response()->noContent()->header('Cache-Control', 'no-store');

        if (! config('analytics.enabled') || ! $this->originAllowed($request)) {
            return $empty;
        }

        $raw = $request->getContent();
        if ($raw === '' || strlen($raw) > 64 * 1024) {
            return $empty;
        }

        $data = json_decode($raw, true, 6);
        if (is_array($data)) {
            try {
                $ingestor->handle($request, $data);
            } catch (\Throwable $e) {
                report($e); // telemetry must never break the visitor's page
            }
        }

        return $empty;
    }

    private function originAllowed(Request $request): bool
    {
        $origin = $request->headers->get('Origin');
        if ($origin === null) {
            return true; // some beacons omit it for same-origin requests
        }
        $host = parse_url($origin, PHP_URL_HOST);
        $allowed = array_merge([$request->getHost()], (array) config('analytics.allowed_hosts', []));

        return is_string($host) && in_array($host, $allowed, true);
    }
}
