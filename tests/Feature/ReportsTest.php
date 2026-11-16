<?php

declare(strict_types=1);

use Webstat\Analytics\Enums\Audience;
use Webstat\Analytics\Enums\Preset;
use Webstat\Analytics\Models\Goal;
use Webstat\Analytics\Reports\AnalyticsReports;
use Webstat\Analytics\Reports\DateRange;
use Webstat\Analytics\Reports\Filters;

function wa_filters(Audience $a = Audience::Humans, Preset $p = Preset::Day): Filters
{
    return new Filters(DateRange::make($p), $a);
}

function wa_reports(): AnalyticsReports
{
    return app(AnalyticsReports::class);
}

it('builds overview, timeseries and audience splits', function (): void {
    wa_seed_session(['is_new' => true, 'country' => 'NL', 'city' => 'Amsterdam'], [['title' => 'Home'], ['path' => '/b']]);
    wa_seed_session(['is_new' => false, 'country' => 'RU', 'age_group' => '25-34', 'gender' => 'female'], [[]]);
    wa_seed_session(['is_bot' => true, 'bot_name' => 'Googlebot'], [[]]);

    $r = wa_reports();
    $f = wa_filters();

    expect($r->overview($f)['sessions']['value'])->toBe(2)
        ->and($r->overview($f)['pageviews']['value'])->toBe(3)
        ->and($r->overview(wa_filters(Audience::All))['sessions']['value'])->toBe(3)
        ->and($r->humanVsBot($f))->toBe(['humans' => 2, 'bots' => 1])
        ->and($r->newVsReturning($f))->toMatchArray(['new_sessions' => 1, 'returning_sessions' => 1, 'new_users' => 1, 'returning_users' => 1])
        ->and(array_sum($r->timeseries($f)['pageviews']))->toBe(3)
        ->and(array_column($r->geo($f)['countries'], 'value'))->toBe([1, 1])
        ->and($r->demographics($f)['gender'][0]['label'])->toBeIn(['female', 'unknown']);
});

it('reports device models, OS versions, screens, IPs and recent visits', function (): void {
    foreach (range(1, 2) as $_) {
        wa_seed_session(['ip' => '8.8.4.4', 'country' => 'US', 'device' => 'mobile', 'device_brand' => 'Apple', 'device_model' => 'iPhone',
            'os' => 'iOS', 'os_version' => '17', 'screen' => '390x844', 'browser' => 'Safari']);
    }
    wa_seed_session(['ip' => '1.1.1.1', 'device' => 'desktop', 'device_model' => 'PC', 'os' => 'Windows', 'os_version' => '10/11', 'screen' => '1920x1080']);

    $r = wa_reports();
    $f = wa_filters();
    $tech = $r->tech($f);

    expect($tech['models'][0])->toMatchArray(['label' => 'Apple iPhone', 'value' => 2])
        ->and($tech['os_versions'][0])->toMatchArray(['label' => 'iOS 17', 'value' => 2])
        ->and($tech['screens'][0])->toMatchArray(['label' => '390x844', 'value' => 2])
        ->and($r->topIps($f)[0])->toMatchArray(['label' => '8.8.4.4', 'value' => 2])
        ->and($r->recentVisits($f))->toHaveCount(3)
        ->and($r->geoCoverage($f))->toMatchArray(['total' => 3, 'with_country' => 2, 'with_ip' => 3]);
});

it('aggregates click, scroll, view and link maps', function (): void {
    wa_seed_session([], [
        ['type' => 'click', 'path' => '/p', 'x' => 10, 'y' => 20, 'ph' => 3000, 'target' => 'a.buy', 'label' => 'Buy'],
        ['type' => 'click', 'path' => '/p', 'x' => 10, 'y' => 20, 'ph' => 1000, 'target' => 'a.buy'],
        ['type' => 'link', 'path' => '/p', 'target' => 'https://x.test/a'],
        ['type' => 'engage', 'path' => '/p', 'value' => 100, 'aux' => 0b11],
        ['type' => 'engage', 'path' => '/p', 'value' => 40, 'aux' => 0b01],
    ]);
    $r = wa_reports();
    $f = wa_filters();

    $click = $r->clickMap($f, '/p');
    expect($click['cells'])->toBe([[10, 20, 2]])->and($click['height'])->toBe(2000)->and($click['targets'][0]['value'])->toBe(2);

    $scroll = $r->scrollMap($f, '/p');
    expect($scroll['total'])->toBe(2)->and($scroll['reach'][40])->toBe(100.0)->and($scroll['reach'][50])->toBe(50.0);

    $view = $r->viewMap($f, '/p');
    expect($view['bands'][0])->toBe(100.0)->and($view['bands'][1])->toBe(50.0)->and($view['bands'][2])->toBe(0.0);

    expect($r->linkMap($f, '/p')[0]['label'])->toBe('https://x.test/a');
});

it('computes form conversion and field drop-off', function (): void {
    foreach ([true, false, false] as $submit) {
        wa_seed_session([], array_filter([
            ['type' => 'form_start', 'target' => '#signup'],
            ['type' => 'form_field', 'target' => '#signup', 'label' => 'email'],
            $submit ? ['type' => 'form_submit', 'target' => '#signup'] : ['type' => 'form_abandon', 'target' => '#signup', 'label' => 'email'],
        ]));
    }
    $r = wa_reports();
    $form = $r->forms(wa_filters())[0];

    expect($form)->toMatchArray(['form' => '#signup', 'starts' => 3, 'submits' => 1, 'abandons' => 2, 'conversion' => 33.3])
        ->and($r->formFields(wa_filters(), '#signup')[0])->toMatchArray(['field' => 'email', 'users' => 3, 'abandoned_here' => 2]);
});

it('measures goals and a sequential funnel', function (): void {
    Goal::create(['name' => 'Pricing', 'type' => 'path', 'match' => '/pricing*', 'funnel_step' => 1]);
    Goal::create(['name' => 'Signup', 'type' => 'event', 'match' => 'signup', 'funnel_step' => 2]);
    Goal::create(['name' => 'Buy click', 'type' => 'click', 'match' => '#buy']);
    Goal::create(['name' => 'Off', 'type' => 'path', 'match' => '/', 'is_active' => false]);

    wa_seed_session([], [['path' => '/pricing/pro'], ['type' => 'goal', 'target' => 'signup', 'value' => 49], ['type' => 'click', 'target' => 'a#buy']]);
    wa_seed_session([], [['path' => '/pricing']]);
    wa_seed_session([], [['type' => 'goal', 'target' => 'signup']]); // signup without visiting pricing: not part of the funnel

    $goals = collect(wa_reports()->goals(wa_filters()))->keyBy('name');
    expect($goals)->toHaveCount(3)
        ->and($goals['Pricing'])->toMatchArray(['sessions' => 2, 'rate' => 66.67])
        ->and($goals['Signup'])->toMatchArray(['sessions' => 2, 'value' => 49])
        ->and($goals['Buy click']['sessions'])->toBe(1);

    $funnel = wa_reports()->funnel(wa_filters());
    expect(array_column($funnel, 'count'))->toBe([2, 1])
        ->and($funnel[1]['of_prev'])->toBe(50.0);
});

it('lists the most common page transitions', function (): void {
    foreach (range(1, 2) as $_) {
        wa_seed_session([], [['path' => '/'], ['path' => '/pricing']]);
    }
    expect(wa_reports()->paths(wa_filters())[0])->toMatchArray(['label' => '/', 'hint' => '/pricing', 'value' => 2]);
});

it('builds period presets with matching granularity and previous period', function (): void {
    expect(DateRange::make(Preset::Day)->granularity->value)->toBe('hour')
        ->and(DateRange::make(Preset::Month)->granularity->value)->toBe('day')
        ->and(DateRange::make(Preset::Year)->granularity->value)->toBe('week')
        ->and(count(DateRange::make(Preset::Week)->buckets()))->toBe(7);

    $r = DateRange::make(Preset::Week);
    expect($r->previous()->to->lt($r->from))->toBeTrue();
});
