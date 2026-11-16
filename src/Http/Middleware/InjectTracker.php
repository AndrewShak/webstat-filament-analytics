<?php

declare(strict_types=1);

namespace Webstat\Analytics\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional & isolated: only touches successful, full-page HTML GET responses.
 * Register explicitly (alias "analytics.inject") or via analytics.inject.global.
 */
final class InjectTracker
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('analytics.inject.enabled') || ! config('analytics.enabled')) {
            return $response;
        }
        if (! $request->isMethod('GET') || $request->ajax() || $request->headers->has('X-Livewire')
            || $request->routeIs('filament.*') || $response->getStatusCode() !== 200
            || ! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }
        foreach ((array) config('analytics.ignore_paths', []) as $p) {
            if (Str::is($p, ltrim($request->path(), '/'))) {
                return $response;
            }
        }

        $html = $response->getContent();
        if (! is_string($html) || str_contains($html, '__WA__') || ($pos = strripos($html, '</body>')) === false) {
            return $response;
        }

        $response->setContent(substr($html, 0, $pos) . Blade::render('<x-analytics::tracker />') . substr($html, $pos));
        $response->headers->remove('Content-Length');

        return $response;
    }
}
