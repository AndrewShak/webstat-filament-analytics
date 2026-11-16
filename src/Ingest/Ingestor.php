<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Webstat\Analytics\Ingest\Buffer\EventBuffer;
use Webstat\Analytics\Ingest\Geo\GeoResolver;

/** Validates + enriches one telemetry payload and hands it to the buffer. Never throws on bad input. */
final class Ingestor
{
    private const TYPES = ['pageview', 'click', 'link', 'engage', 'form_start', 'form_field', 'form_submit', 'form_abandon', 'goal'];
    private const MAX_EVENTS = 40;

    public function __construct(
        private readonly BotDetector $bots,
        private readonly UserAgentParser $ua,
        private readonly ReferrerClassifier $refs,
        private readonly GeoResolver $geo,
        private readonly DemographicsEnricher $demographics,
        private readonly EventBuffer $buffer,
    ) {}

    /** @param array<string, mixed> $p */
    public function handle(Request $request, array $p): void
    {
        $vid = $this->hex($p['vid'] ?? null, 16, 36);
        $sid = $this->hex($p['sid'] ?? null, 32, 32);
        if ($vid === null || $sid === null || ! is_array($p['e'] ?? null)) {
            return;
        }

        $uaString = mb_substr((string) $request->userAgent(), 0, 512);
        $bot = $this->bots->inspect($uaString, $request, (int) ($p['sig'] ?? 0));
        $isBot = $bot['score'] >= (int) config('analytics.bots.threshold', 50);
        if ($isBot && ! config('analytics.bots.store', true)) {
            return;
        }

        $now = now()->format('Y-m-d H:i:s');
        $events = [];
        $pageview = null;
        foreach (array_slice($p['e'], 0, self::MAX_EVENTS) as $raw) {
            if (! is_array($raw) || ($ev = $this->event($raw, $sid, $now, $isBot)) === null) {
                continue;
            }
            if ($ev['type'] === 'pageview' && $pageview === null) {
                $pageview = ['row' => $ev, 'ref' => $this->str($raw['ref'] ?? null, 500), 'utm' => $this->utm($raw['data'] ?? null)];
            }
            $events[] = $ev;
        }
        if ($events === []) {
            return;
        }

        $first = $pageview['row'] ?? $events[0];
        $classified = $this->refs->classify($pageview['ref'] ?? null, $request->getHost(), $pageview['utm'] ?? []);
        $ip = ClientIp::resolve($request);
        $geo = $this->geo->resolve($request, $ip);
        $parsed = $this->ua->parse($uaString);
        $screen = $this->screen($p['sw'] ?? null, $p['sh'] ?? null);

        $session = [
            'sid' => $sid,
            'visitor_id' => $vid,
            'ip' => ClientIp::forStorage($ip),
            'started_at' => $now,
            'last_seen_at' => $now,
            'pageviews' => 0,
            'events' => 0,
            'duration' => 0,
            'is_bot' => $isBot,
            'bot_name' => $isBot ? $this->str($bot['name'], 64) : null,
            'bot_score' => $bot['score'],
            'is_new' => (bool) ($p['nv'] ?? true),
            'country' => $geo['country'],
            'city' => $geo['city'],
            'browser' => $parsed['browser'],
            'browser_version' => $parsed['browser_version'],
            'os' => $parsed['os'],
            'os_version' => $parsed['os_version'],
            'device' => $isBot ? 'bot' : $parsed['device'],
            'device_brand' => $isBot ? null : $parsed['device_brand'],
            'device_model' => $isBot ? null : $parsed['device_model'],
            'screen' => $screen,
            'lang' => $this->str($p['lang'] ?? null, 8),
            'source_type' => $classified['source_type'],
            'referrer_host' => $classified['referrer_host'],
            'search_engine' => $classified['search_engine'],
            'landing_path' => $first['path'],
            'landing_title' => $first['title'],
            'age_group' => null,
            'gender' => null,
            'meta' => $pageview && $pageview['utm'] ? json_encode($pageview['utm']) : null,
        ];

        $extra = $this->demographics->enrich($request, $session);
        $session['age_group'] = $this->str($extra['age_group'] ?? null, 16);
        $session['gender'] = $this->str($extra['gender'] ?? null, 16);

        // Transient (never stored): lets the Writer do an online country lookup after the response is sent.
        if ($session['country'] === null && $ip !== null && config('analytics.geo.http.enabled')) {
            $session['_lookup_ip'] = $ip;
        }

        $this->buffer->push(['s' => $session, 'e' => $events]);
    }

    /** @param array<string, mixed> $r @return array<string, mixed>|null */
    private function event(array $r, string $sid, string $now, bool $isBot): ?array
    {
        $type = $r['type'] ?? null;
        if (! is_string($type) || ! in_array($type, self::TYPES, true)) {
            return null;
        }
        $path = $this->path($r['path'] ?? null);
        if ($path === null) {
            return null;
        }
        foreach ((array) config('analytics.ignore_paths', []) as $pattern) {
            if (Str::is($pattern, ltrim($path, '/'))) {
                return null;
            }
        }

        $data = null;
        if ($type === 'link' || $type === 'goal') {
            $data = $this->data($r['data'] ?? null);
        }

        return [
            'sid' => $sid,
            'created_at' => $now,
            'type' => $type,
            'is_bot' => $isBot,
            'path' => $path,
            'title' => $this->str($r['title'] ?? null, 255),
            'target' => $this->str($r['target'] ?? null, 255),
            'label' => $this->str($r['label'] ?? null, 255),
            'x' => $this->int($r['x'] ?? null, 0, 100),
            'y' => $this->int($r['y'] ?? null, 0, 100),
            'ph' => $this->int($r['ph'] ?? null, 0, 1_000_000),
            'value' => $this->int($r['value'] ?? null, -1_000_000_000, 1_000_000_000),
            'aux' => $this->int($r['aux'] ?? null, 0, (1 << 20) - 1),
            'dur' => $this->int($r['dur'] ?? null, 0, 86_400),
            'data' => $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE),
        ];
    }

    private function screen(mixed $w, mixed $h): ?string
    {
        $w = $this->int($w, 0, 20000);
        $h = $this->int($h, 0, 20000);

        return $w !== null && $h !== null && $w >= 100 && $h >= 100 ? $w . 'x' . $h : null;
    }

    private function hex(mixed $v, int $min, int $max): ?string
    {
        return is_string($v) && preg_match('/^[a-f0-9]{' . $min . ',' . $max . '}$/', $v) ? $v : null;
    }

    private function str(mixed $v, int $max): ?string
    {
        if (! is_string($v) || $v === '') {
            return null;
        }
        $v = preg_replace('/[\x00-\x1F\x7F]/u', '', $v) ?? '';

        return $v === '' ? null : mb_substr(trim($v), 0, $max);
    }

    private function int(mixed $v, int $min, int $max): ?int
    {
        return is_numeric($v) ? max($min, min($max, (int) $v)) : null;
    }

    private function path(mixed $v): ?string
    {
        $p = $this->str($v, 255);
        if ($p === null) {
            return null;
        }
        $p = '/' . ltrim(explode('#', $p)[0], '/');
        // keep only whitelisted query params (client already filtered; enforce again)
        [$base, $query] = array_pad(explode('?', $p, 2), 2, '');
        $keep = (array) config('analytics.privacy.keep_query', []);
        if ($query !== '' && $keep !== []) {
            parse_str($query, $q);
            $q = array_intersect_key($q, array_flip($keep));
            $base .= $q ? '?' . http_build_query($q) : '';
        }

        return $base;
    }

    /** @return array<string, string> */
    private function utm(mixed $d): array
    {
        $out = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $k) {
            if (is_array($d) && ($s = $this->str($d[$k] ?? null, 64)) !== null) {
                $out[$k] = $s;
            }
        }

        return $out;
    }

    /** @return array<string, scalar>|null  scalar values only, small */
    private function data(mixed $d): ?array
    {
        if (! is_array($d)) {
            return null;
        }
        $out = [];
        foreach (array_slice($d, 0, 10, true) as $k => $v) {
            if (is_scalar($v) && is_string($k)) {
                $out[mb_substr($k, 0, 40)] = is_string($v) ? mb_substr($v, 0, 200) : $v;
            }
        }

        return $out ?: null;
    }
}
