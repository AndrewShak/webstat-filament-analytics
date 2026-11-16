<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest;

/** Small, dependency-free UA parser: browser, OS (+version), device type, brand and model. */
final class UserAgentParser
{
    private const ANDROID_BRANDS = [
        'Samsung' => '/^(SM-|GT-|SCH-|SGH-|SAMSUNG)/i',
        'Google' => '/^(Pixel|Nexus)/i',
        'Xiaomi' => '/^(Redmi|Mi |MI |POCO|M\d{4}[A-Z]\d|\d{5,}[A-Z]{1,3}\d?|22\d{6}|23\d{6}|24\d{6}|21\d{6})/i',
        'Huawei' => '/^(HUAWEI|HONOR|[A-Z]{3}-[A-LN]\d{2}|[A-Z]{3}-AL\d{2})/i',
        'OnePlus' => '/^(OnePlus|IN20|IN21|LE2|KB20|CPH2)/i',
        'Oppo' => '/^(OPPO|CPH\d)/i',
        'Realme' => '/^(RMX|realme)/i',
        'Vivo' => '/^(vivo|V\d{4})/i',
        'Motorola' => '/^(moto|XT\d{4})/i',
        'Sony' => '/^(Sony|XQ-|SO-|SOV)/i',
        'Nokia' => '/^Nokia/i',
        'LG' => '/^(LG|LM-)/i',
        'Asus' => '/^(ASUS|ZS\d)/i',
        'Lenovo' => '/^(Lenovo|TB-)/i',
        'Honor' => '/^HONOR/i',
        'Tecno' => '/^(TECNO|Tecno)/i',
        'Infinix' => '/^Infinix/i',
    ];

    /**
     * @return array{browser: string, browser_version: ?string, os: string, os_version: ?string,
     *               device: string, device_brand: ?string, device_model: ?string}
     */
    public function parse(string $ua): array
    {
        [$browser, $bv] = $this->browser($ua);
        [$os, $osv] = $this->os($ua);
        $device = $this->device($ua);
        [$brand, $model] = $this->brandModel($ua, $os, $device);

        return [
            'browser' => $browser, 'browser_version' => $bv, 'os' => $os, 'os_version' => $osv,
            'device' => $device, 'device_brand' => $brand, 'device_model' => $model,
        ];
    }

    /** @return array{0: string, 1: ?string} */
    private function browser(string $ua): array
    {
        $map = [
            'Edge' => '/(?:Edg|EdgA|EdgiOS|Edge)\/([\d.]+)/',
            'Opera' => '/(?:OPR|Opera)\/([\d.]+)/',
            'Yandex Browser' => '/YaBrowser\/([\d.]+)/',
            'Samsung Internet' => '/SamsungBrowser\/([\d.]+)/',
            'Vivaldi' => '/Vivaldi\/([\d.]+)/',
            'Firefox' => '/(?:Firefox|FxiOS)\/([\d.]+)/',
            'Chrome' => '/(?:Chrome|CriOS)\/([\d.]+)/',
            'Safari' => '/Version\/([\d.]+).*Safari\//',
            'IE' => '/(?:MSIE |Trident\/.*rv:)([\d.]+)/',
        ];
        foreach ($map as $name => $re) {
            if (preg_match($re, $ua, $m)) {
                return [$name, explode('.', $m[1])[0]];
            }
        }

        return ['Other', null];
    }

    /** @return array{0: string, 1: ?string} */
    private function os(string $ua): array
    {
        if (preg_match('/Windows NT ([\d.]+)/i', $ua, $m)) {
            $v = ['10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7', '6.0' => 'Vista', '5.1' => 'XP'][$m[1]] ?? $m[1];

            return ['Windows', $v];
        }
        if (preg_match('/Android[ \/]([\d.]+)/i', $ua, $m)) {
            return ['Android', explode('.', $m[1])[0]];
        }
        if (stripos($ua, 'Android') !== false) {
            return ['Android', null];
        }
        if (preg_match('/(?:iPhone|CPU|iPad).*?OS (\d+)[_.]/i', $ua, $m) || preg_match('/iPhone|iPad|iPod/i', $ua, $m)) {
            return ['iOS', $m[1] ?? null];
        }
        if (preg_match('/Mac OS X (\d+)[_.](\d+)/i', $ua, $m)) {
            return ['macOS', $m[1] . '.' . $m[2]];
        }
        if (preg_match('/Macintosh/i', $ua)) {
            return ['macOS', null];
        }
        if (stripos($ua, 'CrOS') !== false) {
            return ['ChromeOS', null];
        }
        if (preg_match('/Linux|X11/i', $ua)) {
            return ['Linux', null];
        }

        return ['Other', null];
    }

    private function device(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/SmartTV|SMART-TV|Tizen|WebOS|AppleTV|Roku|HbbTV/i', $ua) => 'tv',
            (bool) preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua),
            str_contains($ua, 'Android') && ! str_contains($ua, 'Mobile') => 'tablet',
            (bool) preg_match('/Mobi|iPhone|iPod|Android/i', $ua) => 'mobile',
            default => 'desktop',
        };
    }

    /** @return array{0: ?string, 1: ?string} */
    private function brandModel(string $ua, string $os, string $device): array
    {
        if (preg_match('/iPhone|iPad|iPod/i', $ua, $m)) {
            return ['Apple', stripos($m[0], 'ipad') !== false ? 'iPad' : (stripos($m[0], 'ipod') !== false ? 'iPod' : 'iPhone')];
        }
        if ($os === 'Android') {
            $model = null;
            if (preg_match('/Android [\d.]+; ([^;)]+?)(?: Build|\)|;)/', $ua, $m)) {
                $model = trim($m[1]);
            }
            // Chrome's reduced UA reports the placeholder "K" — no real model available.
            if ($model === null || $model === 'K' || preg_match('/^(Android|Mobile|wv|U)$/i', $model)) {
                return [null, null];
            }
            foreach (self::ANDROID_BRANDS as $brand => $re) {
                if (preg_match($re, $model)) {
                    return [$brand, mb_substr($model, 0, 48)];
                }
            }

            return [null, mb_substr($model, 0, 48)];
        }
        if ($os === 'macOS') {
            return ['Apple', 'Mac'];
        }
        if ($os === 'ChromeOS') {
            return ['Google', 'Chromebook'];
        }
        if ($os === 'Windows') {
            return [null, 'PC'];
        }
        if ($os === 'Linux') {
            return [null, 'PC'];
        }

        return [null, null];
    }
}
