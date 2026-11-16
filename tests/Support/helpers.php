<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

const WA_SID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const WA_CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

function wa_payload(array $events, string $sid = WA_SID, array $extra = []): string
{
    return json_encode(array_merge(['v' => 1, 'vid' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'sid' => $sid, 'nv' => 1, 'sig' => 0, 'lang' => 'ru-RU', 'sw' => 1920, 'sh' => 1080, 'e' => $events], $extra));
}

function wa_server(array $extra = []): array
{
    return $extra + ['HTTP_USER_AGENT' => WA_CHROME, 'HTTP_ACCEPT_LANGUAGE' => 'ru-RU', 'HTTP_SEC_FETCH_SITE' => 'same-origin'];
}

/** Insert a session + events straight into the DB (for report tests). */
function wa_seed_session(array $session = [], array $events = []): string
{
    static $n = 0;
    $sid = str_pad(dechex(++$n + 4096), 32, '0', STR_PAD_LEFT);
    $now = now()->format('Y-m-d H:i:s');
    DB::table('analytics_sessions')->insert(array_merge([
        'sid' => $sid, 'visitor_id' => 'v' . str_pad((string) $n, 16, '0', STR_PAD_LEFT), 'started_at' => $now, 'last_seen_at' => $now,
        'is_bot' => false, 'is_new' => true, 'source_type' => 'direct', 'pageviews' => 1,
    ], $session));
    foreach ($events as $e) {
        DB::table('analytics_events')->insert(array_merge([
            'sid' => $sid, 'created_at' => $now, 'is_bot' => (bool) ($session['is_bot'] ?? false), 'type' => 'pageview', 'path' => '/',
        ], $e));
    }

    return $sid;
}

/** @return array<string, true> dotted keys of a nested translation array */
function wa_flatten(array $a, string $prefix = ''): array
{
    $out = [];
    foreach ($a as $k => $v) {
        $out = is_array($v) ? $out + wa_flatten($v, $prefix . $k . '.') : $out + [$prefix . $k => true];
    }

    return $out;
}
