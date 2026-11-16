<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Webstat\Analytics\Ingest\BotDetector;
use Webstat\Analytics\Ingest\ReferrerClassifier;
use Webstat\Analytics\Ingest\UserAgentParser;
use Webstat\Analytics\Support\LikePattern;

dataset('devices', [
    'iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ['browser' => 'Safari', 'os' => 'iOS', 'os_version' => '17', 'device' => 'mobile', 'device_brand' => 'Apple', 'device_model' => 'iPhone']],
    'iPad' => ['Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ['os' => 'iOS', 'device' => 'tablet', 'device_brand' => 'Apple', 'device_model' => 'iPad']],
    'Samsung' => ['Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        ['browser' => 'Chrome', 'os' => 'Android', 'os_version' => '14', 'device' => 'mobile', 'device_brand' => 'Samsung', 'device_model' => 'SM-S918B']],
    'Pixel' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        ['device_brand' => 'Google', 'device_model' => 'Pixel 8']],
    'Android reduced UA' => ['Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36',
        ['os' => 'Android', 'device' => 'mobile', 'device_brand' => null, 'device_model' => null]],
    'Windows Chrome' => [WA_CHROME,
        ['browser' => 'Chrome', 'browser_version' => '130', 'os' => 'Windows', 'os_version' => '10/11', 'device' => 'desktop', 'device_model' => 'PC']],
    'Mac Safari' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
        ['browser' => 'Safari', 'os' => 'macOS', 'device' => 'desktop', 'device_brand' => 'Apple', 'device_model' => 'Mac']],
    'Yandex Browser' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 YaBrowser/24.9.0.0 Safari/537.36',
        ['browser' => 'Yandex Browser']],
]);

it('detects browser, OS, device type, brand and model', function (string $ua, array $expected): void {
    expect((new UserAgentParser())->parse($ua))->toMatchArray($expected);
})->with('devices');

it('flags known crawlers and keeps real browsers', function (): void {
    $d = new BotDetector();
    $req = Request::create('/', 'POST', server: ['HTTP_ACCEPT_LANGUAGE' => 'ru', 'HTTP_SEC_FETCH_SITE' => 'same-origin']);

    expect($d->inspect('Mozilla/5.0 (compatible; Googlebot/2.1)', $req, 0)['score'])->toBe(100)
        ->and($d->inspect(WA_CHROME, $req, 0)['score'])->toBeLessThan(50)
        ->and($d->inspect(WA_CHROME, $req, 1 | 16)['score'])->toBeGreaterThanOrEqual(50)
        ->and($d->inspect('', $req, 0)['score'])->toBe(100);
});

it('classifies referrers', function (): void {
    $c = new ReferrerClassifier();
    expect($c->classify('https://www.google.com/search?q=x', 'example.com')['source_type'])->toBe('search')
        ->and($c->classify('https://ya.ru/', 'example.com')['search_engine'])->toBe('Yandex')
        ->and($c->classify('https://t.me/chan', 'example.com')['source_type'])->toBe('social')
        ->and($c->classify('https://example.com/a', 'example.com')['source_type'])->toBe('direct')
        ->and($c->classify('https://blog.other.org/x', 'example.com')['source_type'])->toBe('referral')
        ->and($c->classify(null, 'example.com', ['utm_source' => 'nl'])['source_type'])->toBe('campaign');
});

it('escapes LIKE patterns', function (): void {
    expect(LikePattern::wildcard('/blog/*'))->toBe('/blog/%')
        ->and(LikePattern::contains('50%_off'))->toBe('%50!%!_off%');
});
