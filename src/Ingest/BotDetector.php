<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest;

use Illuminate\Http\Request;

/**
 * Lightweight bot scoring: known crawlers (UA), header sanity, client automation signals.
 * Score 0..100; >= analytics.bots.threshold => bot.
 */
final class BotDetector
{
    private const KNOWN = [
        'googlebot' => 'Googlebot', 'adsbot-google' => 'AdsBot', 'google-inspectiontool' => 'Google Inspection',
        'bingbot' => 'Bingbot', 'bingpreview' => 'BingPreview', 'yandex' => 'Yandex bot', 'baiduspider' => 'Baidu',
        'duckduckbot' => 'DuckDuckBot', 'slurp' => 'Yahoo Slurp', 'applebot' => 'Applebot', 'petalbot' => 'PetalBot',
        'semrush' => 'SemrushBot', 'ahrefs' => 'AhrefsBot', 'mj12bot' => 'MJ12bot', 'dotbot' => 'DotBot',
        'bytespider' => 'Bytespider', 'gptbot' => 'GPTBot', 'chatgpt-user' => 'ChatGPT', 'claudebot' => 'ClaudeBot',
        'claude-web' => 'Claude', 'ccbot' => 'CCBot', 'perplexitybot' => 'PerplexityBot', 'amazonbot' => 'Amazonbot',
        'facebookexternalhit' => 'Facebook', 'twitterbot' => 'Twitterbot', 'linkedinbot' => 'LinkedInBot',
        'slackbot' => 'Slackbot', 'telegrambot' => 'TelegramBot', 'whatsapp' => 'WhatsApp preview',
        'discordbot' => 'Discordbot', 'pingdom' => 'Pingdom', 'uptimerobot' => 'UptimeRobot', 'statuscake' => 'StatusCake',
        'chrome-lighthouse' => 'Lighthouse', 'headlesschrome' => 'HeadlessChrome', 'phantomjs' => 'PhantomJS',
        'puppeteer' => 'Puppeteer', 'playwright' => 'Playwright', 'selenium' => 'Selenium',
        'curl/' => 'curl', 'wget/' => 'wget', 'python-requests' => 'python-requests', 'python-urllib' => 'urllib',
        'aiohttp' => 'aiohttp', 'httpx' => 'httpx', 'go-http-client' => 'Go-http-client', 'java/' => 'Java',
        'libwww' => 'libwww', 'scrapy' => 'Scrapy', 'node-fetch' => 'node-fetch', 'axios/' => 'axios',
        'postmanruntime' => 'Postman', 'okhttp' => 'okhttp',
    ];

    /** @return array{score: int, name: ?string} */
    public function inspect(string $ua, Request $request, int $sig): array
    {
        $lower = strtolower($ua);
        if ($lower === '') {
            return ['score' => 100, 'name' => 'Empty UA'];
        }

        $extra = (array) config('analytics.bots.patterns', []);
        foreach ($extra + self::KNOWN as $needle => $name) {
            if (str_contains($lower, strtolower((string) $needle))) {
                return ['score' => 100, 'name' => (string) $name];
            }
        }
        if (preg_match('/(bot|crawl|spider|slurp|scrape|headless)/i', $ua)) {
            return ['score' => 90, 'name' => 'Generic bot'];
        }

        $score = 0;
        $name = null;
        if (strlen($ua) < 24 || ! str_starts_with($ua, 'Mozilla/')) {
            $score += 40;
        }
        if (! $request->headers->has('Accept-Language')) {
            $score += 30;
        }
        // Chromium >= 80 always sends Sec-Fetch-* headers.
        if (preg_match('/Chrome\/(\d+)/', $ua, $m) && (int) $m[1] >= 80 && ! $request->headers->has('Sec-Fetch-Site')) {
            $score += 25;
        }
        $weights = [1 => 60, 2 => 60, 4 => 20, 8 => 30, 16 => 50, 32 => 15];
        foreach ($weights as $bit => $w) {
            if ($sig & $bit) {
                $score += $w;
                $name ??= 'Automation';
            }
        }

        return ['score' => min(100, $score), 'name' => $score >= 50 ? ($name ?? 'Suspicious') : null];
    }
}
