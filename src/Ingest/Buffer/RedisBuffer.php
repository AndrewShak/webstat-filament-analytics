<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest\Buffer;

use Illuminate\Support\Facades\Redis;

/** O(1) RPUSH per request; drained in bulk by `analytics:flush`. */
final class RedisBuffer implements EventBuffer
{
    private const LUA_DRAIN = "local i = redis.call('LRANGE', KEYS[1], 0, ARGV[1]-1) redis.call('LTRIM', KEYS[1], ARGV[1], -1) return i";

    private function redis(): \Illuminate\Redis\Connections\Connection
    {
        return Redis::connection(config('analytics.buffer.redis_connection'));
    }

    private function key(): string
    {
        return (string) config('analytics.buffer.key');
    }

    public function push(array $entry): void
    {
        try {
            $r = $this->redis();
            if ($r->llen($this->key()) >= (int) config('analytics.buffer.max_length', 250000)) {
                return; // back-pressure: drop rather than exhaust memory
            }
            $r->rpush($this->key(), json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @return list<array<string, mixed>> */
    public function drain(int $max): array
    {
        $raw = $this->redis()->eval(self::LUA_DRAIN, 1, $this->key(), $max);
        $out = [];
        foreach ((array) $raw as $line) {
            $d = json_decode((string) $line, true);
            if (is_array($d) && isset($d['s'], $d['e'])) {
                $out[] = $d;
            }
        }

        return $out;
    }

    public function length(): int
    {
        return (int) $this->redis()->llen($this->key());
    }
}
