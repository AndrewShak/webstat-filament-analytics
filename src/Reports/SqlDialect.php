<?php

declare(strict_types=1);

namespace Webstat\Analytics\Reports;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;
use Webstat\Analytics\Enums\Granularity;

/** Portable time-bucketing expressions (PostgreSQL, MySQL/MariaDB, SQLite). */
final class SqlDialect
{
    /** @param string $col trusted, internal column name */
    public static function bucket(ConnectionInterface $c, string $col, Granularity $g): string
    {
        return match ($c->getDriverName()) {
            'pgsql' => match ($g) {
                Granularity::Hour => "to_char(date_trunc('hour', {$col}), 'YYYY-MM-DD HH24:00:00')",
                Granularity::Day => "to_char({$col}, 'YYYY-MM-DD')",
                Granularity::Week => "to_char(date_trunc('week', {$col}), 'YYYY-MM-DD')",
                Granularity::Month => "to_char(date_trunc('month', {$col}), 'YYYY-MM-DD')",
            },
            'mysql', 'mariadb' => match ($g) {
                Granularity::Hour => "DATE_FORMAT({$col}, '%Y-%m-%d %H:00:00')",
                Granularity::Day => "DATE_FORMAT({$col}, '%Y-%m-%d')",
                Granularity::Week => "DATE_FORMAT(DATE_SUB({$col}, INTERVAL WEEKDAY({$col}) DAY), '%Y-%m-%d')",
                Granularity::Month => "DATE_FORMAT({$col}, '%Y-%m-01')",
            },
            'sqlite' => match ($g) {
                Granularity::Hour => "strftime('%Y-%m-%d %H:00:00', {$col})",
                Granularity::Day => "strftime('%Y-%m-%d', {$col})",
                Granularity::Week => "date({$col}, '-' || ((cast(strftime('%w', {$col}) as integer) + 6) % 7) || ' days')",
                Granularity::Month => "strftime('%Y-%m-01', {$col})",
            },
            default => throw new RuntimeException('Analytics: unsupported database driver ' . $c->getDriverName()),
        };
    }
}
