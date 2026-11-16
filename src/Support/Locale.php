<?php

declare(strict_types=1);

namespace Webstat\Analytics\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Dashboard language (ru/en). Order: ?lang= -> session (user's switch) -> analytics.locale -> app locale.
 * Used both for page content and for the sidebar labels, so the whole analytics UI switches together.
 */
final class Locale
{
    /** @return list<string> */
    public static function supported(): array
    {
        return array_values((array) config('analytics.locales', ['ru', 'en']));
    }

    public static function current(): string
    {
        $supported = self::supported();
        $request = request();
        $hasSession = $request->hasSession();

        $q = $request->query('lang');
        if (is_string($q) && in_array($q, $supported, true)) {
            if ($hasSession) {
                $request->session()->put('analytics.lang', $q);
            }

            return $q;
        }

        $saved = $hasSession ? $request->session()->get('analytics.lang') : null;
        if (is_string($saved) && in_array($saved, $supported, true)) {
            return $saved;
        }

        $cfg = config('analytics.locale');
        if (is_string($cfg) && in_array($cfg, $supported, true)) {
            return $cfg;
        }

        $app = app()->getLocale();

        return in_array($app, $supported, true) ? $app : ($supported[0] ?? 'en');
    }

    public static function set(string $locale): bool
    {
        if (! in_array($locale, self::supported(), true) || ! request()->hasSession()) {
            return false;
        }
        request()->session()->put('analytics.lang', $locale);

        return true;
    }

    /** Applies the current dashboard language to this request (translator + Carbon dates). */
    public static function apply(): string
    {
        $l = self::current();
        app()->setLocale($l);
        Carbon::setLocale($l);
        CarbonImmutable::setLocale($l);

        return $l;
    }

    /** Translate a package key in the dashboard language, independent of the app locale. */
    public static function t(string $key, array $replace = []): string
    {
        return (string) trans('analytics::analytics.' . $key, $replace, self::current());
    }
}
