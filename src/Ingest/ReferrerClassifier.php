<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest;

final class ReferrerClassifier
{
    private const SEARCH = [
        'google.' => 'Google', 'yandex.' => 'Yandex', 'ya.ru' => 'Yandex', 'bing.com' => 'Bing',
        'duckduckgo.com' => 'DuckDuckGo', 'yahoo.' => 'Yahoo', 'baidu.com' => 'Baidu', 'ecosia.org' => 'Ecosia',
        'search.brave.com' => 'Brave', 'go.mail.ru' => 'Mail.ru', 'rambler.ru' => 'Rambler', 'seznam.cz' => 'Seznam',
        'naver.com' => 'Naver', 'qwant.com' => 'Qwant', 'startpage.com' => 'Startpage', 'ask.com' => 'Ask',
        'sogou.com' => 'Sogou', 'kagi.com' => 'Kagi',
    ];

    private const SOCIAL = [
        'facebook.com', 'fb.com', 'instagram.com', 't.co', 'twitter.com', 'x.com', 'linkedin.com', 'lnkd.in',
        'vk.com', 'ok.ru', 't.me', 'telegram.org', 'youtube.com', 'youtu.be', 'reddit.com', 'pinterest.',
        'tiktok.com', 'whatsapp.com', 'threads.net', 'dzen.ru',
    ];

    /**
     * @param  array<string, string>  $utm
     * @return array{source_type: string, referrer_host: ?string, search_engine: ?string}
     */
    public function classify(?string $referrer, string $ownHost, array $utm = []): array
    {
        $host = null;
        if ($referrer) {
            $h = parse_url($referrer, PHP_URL_HOST);
            $host = is_string($h) ? strtolower(preg_replace('/^www\./', '', $h)) : null;
            $own = strtolower(preg_replace('/^www\./', '', $ownHost));
            if ($host === $own || ($host && str_ends_with($host, '.' . $own))) {
                $host = null; // internal navigation / same site
            }
        }

        $engine = null;
        $type = 'direct';
        if ($host) {
            $type = 'referral';
            foreach (self::SEARCH as $needle => $name) {
                if (str_contains($host, $needle)) {
                    [$type, $engine] = ['search', $name];
                    break;
                }
            }
            if ($type === 'referral') {
                foreach (self::SOCIAL as $needle) {
                    if ($host === $needle || str_ends_with($host, '.' . $needle) || (str_ends_with($needle, '.') && str_contains($host, $needle))) {
                        $type = 'social';
                        break;
                    }
                }
            }
        }
        if (! empty($utm['utm_source']) || ! empty($utm['utm_medium'])) {
            $type = 'campaign';
        }

        return ['source_type' => $type, 'referrer_host' => $host ? substr($host, 0, 190) : null, 'search_engine' => $engine];
    }
}
