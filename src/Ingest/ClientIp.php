<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest;

use Illuminate\Http\Request;

/** Determines the real visitor IP, also when the app runs behind an untrusted proxy / Docker network. */
final class ClientIp
{
    public static function resolve(Request $request): ?string
    {
        $peer = $request->ip();

        // A public peer address is authoritative (nobody in between): never trust spoofable headers then.
        if ($peer !== null && self::isPublic($peer)) {
            return $peer;
        }

        foreach ((array) config('analytics.privacy.ip_headers', ['CF-Connecting-IP', 'True-Client-IP', 'X-Real-IP', 'X-Forwarded-For']) as $header) {
            $value = $request->headers->get((string) $header);
            if (! is_string($value) || $value === '') {
                continue;
            }
            foreach (explode(',', $value) as $candidate) {
                $candidate = trim($candidate);
                if (self::isPublic($candidate)) {
                    return $candidate;
                }
            }
        }

        return $peer !== null && filter_var($peer, FILTER_VALIDATE_IP) !== false ? $peer : null;
    }

    public static function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /** Applies analytics.privacy.ip (full | anonymized | none). */
    public static function forStorage(?string $ip, ?string $mode = null): ?string
    {
        if ($ip === null) {
            return null;
        }

        return match ($mode ?? (string) config('analytics.privacy.ip', 'full')) {
            'none' => null,
            'anonymized' => self::anonymize($ip),
            default => $ip,
        };
    }

    public static function anonymize(string $ip): ?string
    {
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return null;
        }
        if (strlen($bin) === 4) {
            return substr($ip, 0, (int) strrpos($ip, '.')) . '.0';
        }

        $bin = substr($bin, 0, 6) . str_repeat("\0", 10); // keep /48

        return (string) inet_ntop($bin);
    }
}
