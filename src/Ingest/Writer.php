<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest;

use Illuminate\Support\Facades\DB;
use Webstat\Analytics\Ingest\Geo\IpLookup;

/** Persists buffered entries with a handful of bulk statements inside one transaction. */
final class Writer
{
    public function __construct(private readonly IpLookup $ipLookup) {}

    /** @param list<array{s: array<string, mixed>, e: list<array<string, mixed>>}> $entries */
    public function write(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $sessions = [];
        $events = [];
        $inc = [];
        foreach ($entries as $entry) {
            $s = $entry['s'];
            $sid = (string) $s['sid'];
            $sessions[$sid] ??= $s;
            $inc[$sid] ??= ['events' => 0, 'pageviews' => 0, 'last' => $s['started_at']];
            foreach ($entry['e'] as $ev) {
                $events[] = $ev;
                $inc[$sid]['events']++;
                if ($ev['type'] === 'pageview') {
                    $inc[$sid]['pageviews']++;
                }
                if ($ev['created_at'] > $inc[$sid]['last']) {
                    $inc[$sid]['last'] = $ev['created_at'];
                }
            }
        }
        $sessions = $this->enrichGeo($sessions);
        ksort($inc); // stable lock order => no deadlocks between concurrent flushes

        $db = DB::connection(config('analytics.connection'));
        $db->transaction(function () use ($db, $sessions, $events, $inc): void {
            foreach (array_chunk(array_values($sessions), 200) as $chunk) {
                $db->table('analytics_sessions')->insertOrIgnore($chunk);
            }

            $existing = $db->table('analytics_sessions')->whereIn('sid', array_keys($inc))
                ->get(['sid', 'started_at', 'last_seen_at'])->keyBy('sid');

            foreach ($inc as $sid => $i) {
                $row = $existing[$sid] ?? null;
                $started = $row ? (string) $row->started_at : (string) $sessions[$sid]['started_at'];
                $last = $row && (string) $row->last_seen_at > $i['last'] ? (string) $row->last_seen_at : $i['last'];
                $duration = max(0, strtotime($last) - strtotime($started));
                $db->table('analytics_sessions')->where('sid', $sid)->update([
                    'last_seen_at' => $last,
                    'duration' => $duration,
                    'pageviews' => $db->raw('pageviews + ' . (int) $i['pageviews']),
                    'events' => $db->raw('events + ' . (int) $i['events']),
                ]);
            }

            foreach (array_chunk($events, 500) as $chunk) {
                $db->table('analytics_events')->insert($chunk);
            }
        }, 2);
    }

    /**
     * Fills country/city for new sessions through the (cached) online lookup, then drops the transient IP field.
     * Bounded per batch so a flush never stalls on a slow provider.
     *
     * @param  array<string, array<string, mixed>>  $sessions
     * @return array<string, array<string, mixed>>
     */
    private function enrichGeo(array $sessions): array
    {
        $budget = (int) config('analytics.geo.http.max_lookups_per_batch', 50);
        foreach ($sessions as $sid => $s) {
            $ip = $s['_lookup_ip'] ?? null;
            unset($sessions[$sid]['_lookup_ip']);
            if ($ip === null || $budget <= 0) {
                continue;
            }
            $budget--;
            $geo = $this->ipLookup->lookup((string) $ip);
            $sessions[$sid]['country'] ??= $geo['country'];
            $sessions[$sid]['city'] ??= $geo['city'];
        }

        return $sessions;
    }
}
