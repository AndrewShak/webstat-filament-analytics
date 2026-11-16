<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Webstat\Analytics\Ingest\Geo\IpLookup;
use Webstat\Analytics\Ingest\Writer;

function wa_post(string $body, array $server = []): \Illuminate\Testing\TestResponse
{
    return test()->call('POST', '/_wa/c', [], [], [], wa_server($server), $body);
}

it('stores the session, events, device, screen and visitor IP', function (): void {
    wa_post(wa_payload([
        ['type' => 'pageview', 'path' => '/pricing', 'title' => 'Pricing', 'ref' => 'https://www.google.com/'],
        ['type' => 'click', 'path' => '/pricing', 'target' => 'a.buy', 'x' => 50, 'y' => 40, 'ph' => 2000],
        ['type' => 'goal', 'path' => '/pricing', 'target' => 'signup', 'value' => 10],
        ['type' => 'bogus', 'path' => '/x'],
    ]), ['HTTP_CF_IPCOUNTRY' => 'NL', 'HTTP_CF_IPCITY' => 'Amsterdam', 'HTTP_X_FORWARDED_FOR' => '8.8.4.4'])->assertNoContent();

    expect(DB::table('analytics_sessions')->count())->toBe(1)
        ->and(DB::table('analytics_events')->count())->toBe(3);

    expect((array) DB::table('analytics_sessions')->first())->toMatchArray([
        'ip' => '8.8.4.4', 'country' => 'NL', 'city' => 'Amsterdam', 'source_type' => 'search', 'search_engine' => 'Google',
        'browser' => 'Chrome', 'os' => 'Windows', 'device' => 'desktop', 'device_model' => 'PC', 'screen' => '1920x1080',
        'landing_path' => '/pricing', 'is_bot' => 0, 'pageviews' => 1, 'events' => 3,
    ]);
});

it('detects the country from the IP through the online lookup after the response', function (): void {
    config(['analytics.geo.http.enabled' => true]);
    app()->instance(IpLookup::class, new class implements IpLookup {
        public function lookup(string $ip): array
        {
            return $ip === '8.8.4.4' ? ['country' => 'US', 'city' => 'Mountain View'] : ['country' => null, 'city' => null];
        }
    });

    wa_post(wa_payload([['type' => 'pageview', 'path' => '/']]), ['HTTP_X_FORWARDED_FOR' => '8.8.4.4'])->assertNoContent();

    expect((array) DB::table('analytics_sessions')->first())->toMatchArray(['ip' => '8.8.4.4', 'country' => 'US', 'city' => 'Mountain View']);
});

it('honours the IP privacy mode but still resolves the country', function (string $mode, ?string $stored): void {
    config(['analytics.privacy.ip' => $mode, 'analytics.geo.http.enabled' => true]);
    app()->instance(IpLookup::class, new class implements IpLookup {
        public function lookup(string $ip): array
        {
            return ['country' => 'DE', 'city' => null];
        }
    });

    wa_post(wa_payload([['type' => 'pageview', 'path' => '/']]), ['HTTP_X_FORWARDED_FOR' => '8.8.4.4'])->assertNoContent();

    expect(DB::table('analytics_sessions')->value('ip'))->toBe($stored)
        ->and(DB::table('analytics_sessions')->value('country'))->toBe('DE');
})->with([['full', '8.8.4.4'], ['anonymized', '8.8.4.0'], ['none', null]]);

it('increments an existing session instead of duplicating it', function (): void {
    wa_post(wa_payload([['type' => 'pageview', 'path' => '/a']]))->assertNoContent();
    wa_post(wa_payload([['type' => 'pageview', 'path' => '/b'], ['type' => 'click', 'path' => '/b', 'target' => 'a']]))->assertNoContent();

    expect(DB::table('analytics_sessions')->count())->toBe(1)
        ->and((int) DB::table('analytics_sessions')->value('pageviews'))->toBe(2)
        ->and((int) DB::table('analytics_sessions')->value('events'))->toBe(3)
        ->and(DB::table('analytics_events')->count())->toBe(3);
});

it('flags crawlers as bots and keeps them in separate stats', function (): void {
    test()->call('POST', '/_wa/c', [], [], [], ['HTTP_USER_AGENT' => 'Googlebot/2.1'], wa_payload([['type' => 'pageview', 'path' => '/']]))->assertNoContent();

    expect((bool) DB::table('analytics_sessions')->value('is_bot'))->toBeTrue()
        ->and(DB::table('analytics_sessions')->value('device'))->toBe('bot')
        ->and((bool) DB::table('analytics_events')->value('is_bot'))->toBeTrue();
});

it('drops bots entirely when analytics.bots.store is false', function (): void {
    config(['analytics.bots.store' => false]);
    test()->call('POST', '/_wa/c', [], [], [], ['HTTP_USER_AGENT' => 'Googlebot/2.1'], wa_payload([['type' => 'pageview', 'path' => '/']]))->assertNoContent();

    expect(DB::table('analytics_events')->count())->toBe(0);
});

it('silently drops garbage, foreign origins, oversized bodies and ignored paths — always 204', function (): void {
    wa_post('{not json')->assertNoContent();
    wa_post(wa_payload([['type' => 'pageview', 'path' => '/']]), ['HTTP_ORIGIN' => 'https://evil.test'])->assertNoContent();
    wa_post(str_repeat('x', 70_000))->assertNoContent();
    wa_post(wa_payload([['type' => 'pageview', 'path' => '/livewire/update']]))->assertNoContent();
    wa_post(json_encode(['vid' => 'zzz', 'sid' => 'bad', 'e' => [['type' => 'pageview', 'path' => '/']]]))->assertNoContent();

    expect(DB::table('analytics_events')->count())->toBe(0)->and(DB::table('analytics_sessions')->count())->toBe(0);
});

it('does nothing when analytics is disabled', function (): void {
    config(['analytics.enabled' => false]);
    // Route is registered at boot, so only the controller guard matters here.
    $this->call('POST', '/_wa/c', [], [], [], wa_server(), wa_payload([['type' => 'pageview', 'path' => '/']]));
    expect(DB::table('analytics_events')->count())->toBe(0);
});

it('strips query strings from stored paths unless whitelisted', function (): void {
    wa_post(wa_payload([['type' => 'pageview', 'path' => '/p?token=secret&utm_source=x#frag']]))->assertNoContent();
    expect(DB::table('analytics_events')->value('path'))->toBe('/p');
});

it('writes batches through the Writer in bulk', function (): void {
    $writer = app(Writer::class);
    $mk = fn (string $sid, string $path): array => ['s' => [
        'sid' => $sid, 'visitor_id' => 'v' . $sid, 'ip' => null, 'started_at' => now()->format('Y-m-d H:i:s'), 'last_seen_at' => now()->format('Y-m-d H:i:s'),
        'pageviews' => 0, 'events' => 0, 'duration' => 0, 'is_bot' => false, 'bot_score' => 0, 'is_new' => true, 'source_type' => 'direct',
    ], 'e' => [[
        'sid' => $sid, 'created_at' => now()->format('Y-m-d H:i:s'), 'type' => 'pageview', 'is_bot' => false, 'path' => $path, 'title' => null,
        'target' => null, 'label' => null, 'x' => null, 'y' => null, 'ph' => null, 'value' => null, 'aux' => null, 'dur' => null, 'data' => null,
    ]]];

    $writer->write([$mk('s1', '/a'), $mk('s2', '/b'), $mk('s1', '/c')]);

    expect(DB::table('analytics_sessions')->count())->toBe(2)
        ->and(DB::table('analytics_events')->count())->toBe(3)
        ->and((int) DB::table('analytics_sessions')->where('sid', 's1')->value('pageviews'))->toBe(2);
});

it('prunes old data only', function (): void {
    wa_seed_session(['started_at' => now()->subDays(500), 'last_seen_at' => now()->subDays(500)], [['created_at' => now()->subDays(500)]]);
    wa_seed_session([], [[]]);

    $this->artisan('analytics:prune', ['--days' => 30])->assertSuccessful();

    expect(DB::table('analytics_sessions')->count())->toBe(1)->and(DB::table('analytics_events')->count())->toBe(1);
});
