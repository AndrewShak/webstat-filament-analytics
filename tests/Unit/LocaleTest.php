<?php

declare(strict_types=1);

use Webstat\Analytics\Support\Locale;

it('follows analytics.locale and translates independent of the app locale', function (): void {
    config(['analytics.locale' => 'ru', 'app.locale' => 'en']);
    app()->setLocale('en');

    expect(Locale::current())->toBe('ru')
        ->and(Locale::t('navigation'))->toBe('Аналитика')
        ->and(Locale::t('tabs.goals'))->toBe('Цели и конверсии');

    config(['analytics.locale' => 'en']);
    expect(Locale::t('navigation'))->toBe('Analytics');
});

it('falls back to the app locale when supported, otherwise the first supported one', function (): void {
    config(['analytics.locale' => null]);
    app()->setLocale('ru');
    expect(Locale::current())->toBe('ru');
    app()->setLocale('de');
    expect(Locale::current())->toBe('ru'); // first of ['ru', 'en']
});

it('ships identical translation keys for ru and en', function (): void {
    $en = require __DIR__ . '/../../resources/lang/en/analytics.php';
    $ru = require __DIR__ . '/../../resources/lang/ru/analytics.php';

    expect(array_keys(wa_flatten($ru)))->toEqualCanonicalizing(array_keys(wa_flatten($en)));
});
