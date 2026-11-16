<?php

declare(strict_types=1);

namespace Webstat\Analytics\Support;

final class TrackerScript
{
    private ?string $js = null;

    /** Tracker source with comments/indentation stripped (cached per process). */
    public function source(): string
    {
        if ($this->js === null) {
            $src = (string) file_get_contents(__DIR__ . '/../../resources/js/tracker.js');
            $src = preg_replace('~/\*.*?\*/~s', '', $src) ?? $src;
            $lines = array_filter(array_map('trim', explode("\n", $src)), static fn (string $l): bool => $l !== '' && ! str_starts_with($l, '//'));
            $this->js = implode("\n", $lines);
        }

        return $this->js;
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        $t = (array) config('analytics.tracking', []);

        return [
            'endpoint' => '/' . trim((string) config('analytics.route.prefix', '_wa'), '/') . '/c',
            'dnt' => (bool) config('analytics.privacy.respect_dnt', true),
            'cookie' => (string) config('analytics.privacy.cookie.name', '_wa_v'),
            'days' => (int) config('analytics.privacy.cookie.days', 365),
            'session' => (int) config('analytics.privacy.session_minutes', 30),
            'keep' => array_values((array) config('analytics.privacy.keep_query', [])),
            'clicks' => (bool) ($t['clicks'] ?? true),
            'links' => (bool) ($t['links'] ?? true),
            'forms' => (bool) ($t['forms'] ?? true),
            'spa' => (bool) ($t['spa'] ?? true),
        ];
    }
}
