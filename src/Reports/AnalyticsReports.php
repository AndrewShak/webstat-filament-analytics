<?php

declare(strict_types=1);

namespace Webstat\Analytics\Reports;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Webstat\Analytics\Enums\Audience;
use Webstat\Analytics\Models\Goal;
use Webstat\Analytics\Support\Countries;

/**
 * All dashboard queries. Plain query builder + covering indexes; results are cached briefly
 * (analytics.filament.cache_seconds) and returned as plain arrays.
 */
final class AnalyticsReports
{
    public const VIEW_BANDS = 20;

    private function conn(): ConnectionInterface
    {
        return DB::connection(config('analytics.connection'));
    }

    private function remember(string $name, Filters $f, \Closure $cb, string $extra = ''): mixed
    {
        $ttl = (int) config('analytics.filament.cache_seconds', 30);
        if ($ttl <= 0) {
            return $cb();
        }

        return Cache::remember('analytics:' . $name . ':' . md5($f->key() . '|' . $extra . '|' . config('analytics.connection')), $ttl, $cb);
    }

    private function sessions(Filters $f, bool $audience = true): Builder
    {
        $q = $this->conn()->table('analytics_sessions')->whereBetween('started_at', [$f->range->from, $f->range->to]);
        if ($audience && $f->audience !== Audience::All) {
            $q->where('is_bot', $f->audience === Audience::Bots);
        }

        return $q;
    }

    private function events(Filters $f, ?string $type = null, bool $audience = true): Builder
    {
        $q = $this->conn()->table('analytics_events')->whereBetween('created_at', [$f->range->from, $f->range->to]);
        if ($type !== null) {
            $q->where('type', $type);
        }
        if ($audience && $f->audience !== Audience::All) {
            $q->where('is_bot', $f->audience === Audience::Bots);
        }

        return $q;
    }

    /** @return list<array{label: string, value: int}> */
    private function top(Builder $q, string $expr, int $limit = 10): array
    {
        return $q->selectRaw("{$expr} as k, count(*) as c")->groupByRaw($expr)->orderByDesc('c')->limit($limit)->get()
            ->map(fn ($r): array => ['label' => (string) ($r->k ?? ''), 'value' => (int) $r->c])->all();
    }

    // ------------------------------------------------------------------ overview

    /** @return array<string, array{value: int, prev: int, delta: ?float}> */
    public function overview(Filters $f): array
    {
        return $this->remember('overview', $f, function () use ($f): array {
            $cur = $this->totals($f);
            $prev = $this->totals(new Filters($f->range->previous(), $f->audience));
            $out = [];
            foreach ($cur as $k => $v) {
                $out[$k] = ['value' => $v, 'prev' => $prev[$k], 'delta' => $prev[$k] > 0 ? round(($v - $prev[$k]) / $prev[$k] * 100, 1) : null];
            }

            return $out;
        });
    }

    /** @return array<string, int> */
    private function totals(Filters $f): array
    {
        $s = $this->sessions($f)->selectRaw('count(*) as sessions, count(distinct visitor_id) as users, coalesce(avg(duration), 0) as avg_duration')->first();

        return [
            'users' => (int) $s->users,
            'sessions' => (int) $s->sessions,
            'pageviews' => $this->events($f, 'pageview')->count(),
            'avg_duration' => (int) round((float) $s->avg_duration),
        ];
    }

    /** @return array{keys: list<string>, labels: list<string>, users: list<int>, sessions: list<int>, pageviews: list<int>} */
    public function timeseries(Filters $f): array
    {
        return $this->remember('timeseries', $f, function () use ($f): array {
            $g = $f->range->granularity;
            $conn = $this->conn();
            $sb = SqlDialect::bucket($conn, 'started_at', $g);
            $eb = SqlDialect::bucket($conn, 'created_at', $g);

            $s = $this->sessions($f)->selectRaw("{$sb} as b, count(*) as sessions, count(distinct visitor_id) as users")->groupBy('b')->get()->keyBy('b');
            $p = $this->events($f, 'pageview')->selectRaw("{$eb} as b, count(*) as pv")->groupBy('b')->get()->keyBy('b');

            $keys = $f->range->buckets();
            $out = ['keys' => $keys, 'labels' => [], 'users' => [], 'sessions' => [], 'pageviews' => []];
            foreach ($keys as $k) {
                $out['labels'][] = $g->display($k);
                $out['users'][] = (int) ($s[$k]->users ?? 0);
                $out['sessions'][] = (int) ($s[$k]->sessions ?? 0);
                $out['pageviews'][] = (int) ($p[$k]->pv ?? 0);
            }

            return $out;
        });
    }

    public function activeNow(): int
    {
        return (int) $this->conn()->table('analytics_events')->where('is_bot', false)
            ->where('created_at', '>=', now()->subMinutes(5))->distinct()->count('sid');
    }

    // ------------------------------------------------------------------ audience

    /** @return array{humans: int, bots: int} */
    public function humanVsBot(Filters $f): array
    {
        return $this->remember('hvb', $f, function () use ($f): array {
            $r = $this->sessions($f, false)->selectRaw('is_bot, count(*) as c')->groupBy('is_bot')->pluck('c', 'is_bot');

            return ['humans' => (int) ($r[0] ?? $r[false] ?? 0), 'bots' => (int) ($r[1] ?? $r[true] ?? 0)];
        });
    }

    /** @return array{new_sessions: int, returning_sessions: int, new_users: int, returning_users: int} */
    public function newVsReturning(Filters $f): array
    {
        return $this->remember('nvr', $f, function () use ($f): array {
            $r = $this->sessions($f)->selectRaw('is_new, count(*) as c')->groupBy('is_new')->pluck('c', 'is_new');
            $newUsers = (int) $this->sessions($f)->where('is_new', true)->distinct()->count('visitor_id');
            $newIds = $this->sessions($f)->where('is_new', true)->select('visitor_id');
            $ret = (int) $this->sessions($f)->where('is_new', false)->whereNotIn('visitor_id', $newIds)->distinct()->count('visitor_id');

            return [
                'new_sessions' => (int) ($r[1] ?? $r[true] ?? 0),
                'returning_sessions' => (int) ($r[0] ?? $r[false] ?? 0),
                'new_users' => $newUsers,
                'returning_users' => $ret,
            ];
        });
    }

    /** @return array{countries: list<array<string, mixed>>, cities: list<array<string, mixed>>} */
    public function geo(Filters $f): array
    {
        return $this->remember('geo', $f, function () use ($f): array {
            $countries = array_map(fn (array $r): array => [
                'label' => trim(Countries::flag($r['label']) . ' ' . Countries::name($r['label'])), 'value' => $r['value'],
            ], $this->top($this->sessions($f)->whereNotNull('country'), 'country'));

            $cities = $this->sessions($f)->whereNotNull('city')->selectRaw('city, country, count(*) as c')
                ->groupBy('city', 'country')->orderByDesc('c')->limit(10)->get()
                ->map(fn ($r): array => ['label' => $r->city, 'hint' => $r->country ? Countries::name($r->country) : null, 'value' => (int) $r->c])->all();

            return ['countries' => $countries, 'cities' => $cities];
        });
    }

    /** @return array{age: list<array<string, mixed>>, gender: list<array<string, mixed>>} */
    public function demographics(Filters $f): array
    {
        return $this->remember('demo', $f, fn (): array => [
            'age' => $this->top($this->sessions($f), "coalesce(age_group, 'unknown')"),
            'gender' => $this->top($this->sessions($f), "coalesce(gender, 'unknown')"),
        ]);
    }

    /** @return array{browsers: list<array>, os: list<array>, os_versions: list<array>, devices: list<array>, models: list<array>, screens: list<array>} */
    public function tech(Filters $f): array
    {
        return $this->remember('tech', $f, function () use ($f): array {
            $models = $this->sessions($f)->whereNotNull('device_model')
                ->selectRaw('device_brand, device_model, max(device) as device, count(*) as c')
                ->groupBy('device_brand', 'device_model')->orderByDesc('c')->limit(10)->get()
                ->map(fn ($r): array => [
                    'label' => trim(($r->device_brand ? $r->device_brand . ' ' : '') . $r->device_model),
                    'hint' => $r->device, 'value' => (int) $r->c,
                ])->all();

            $osVersions = $this->sessions($f)->whereNotNull('os')
                ->selectRaw('os, os_version, count(*) as c')->groupBy('os', 'os_version')->orderByDesc('c')->limit(10)->get()
                ->map(fn ($r): array => ['label' => trim($r->os . ' ' . ($r->os_version ?? '')), 'value' => (int) $r->c])->all();

            return [
                'browsers' => $this->top($this->sessions($f)->whereNotNull('browser'), 'browser'),
                'os' => $this->top($this->sessions($f)->whereNotNull('os'), 'os'),
                'os_versions' => $osVersions,
                'devices' => $this->top($this->sessions($f)->whereNotNull('device'), 'device'),
                'models' => $models,
                'screens' => $this->top($this->sessions($f)->whereNotNull('screen'), 'screen'),
            ];
        });
    }

    /** Latest sessions with IP, location, device and source — "where does this visitor come from". @return list<array<string, mixed>> */
    public function recentVisits(Filters $f, int $limit = 25): array
    {
        return $this->remember('recent', $f, fn (): array => $this->sessions($f)
            ->orderByDesc('started_at')->limit($limit)
            ->get(['started_at', 'ip', 'country', 'city', 'device', 'device_brand', 'device_model', 'browser', 'browser_version', 'os', 'os_version',
                'screen', 'source_type', 'referrer_host', 'search_engine', 'landing_path', 'pageviews', 'is_bot', 'bot_name'])
            ->map(fn ($r): array => (array) $r)->all(), (string) $limit);
    }

    /** Top visitor IPs (only meaningful when analytics.privacy.ip is not "none"). @return list<array{label: string, hint: ?string, value: int}> */
    public function topIps(Filters $f): array
    {
        return $this->remember('topips', $f, fn (): array => $this->sessions($f)->whereNotNull('ip')
            ->selectRaw('ip, max(country) as country, count(*) as c')->groupBy('ip')->orderByDesc('c')->limit(10)->get()
            ->map(fn ($r): array => ['label' => $r->ip, 'hint' => $r->country ? Countries::name($r->country) : null, 'value' => (int) $r->c])->all());
    }

    /** Share of sessions in range that have a country (to tell the user when geo is not configured). */
    public function geoCoverage(Filters $f): array
    {
        return $this->remember('geocov', $f, function () use ($f): array {
            $r = $this->sessions($f)->selectRaw('count(*) as t, sum(case when country is not null then 1 else 0 end) as g, sum(case when ip is not null then 1 else 0 end) as i')->first();

            return ['total' => (int) $r->t, 'with_country' => (int) ($r->g ?? 0), 'with_ip' => (int) ($r->i ?? 0)];
        });
    }

    // ------------------------------------------------------------------ sources & behaviour

    /** @return array<string, list<array<string, mixed>>> */
    public function sources(Filters $f): array
    {
        return $this->remember('sources', $f, function () use ($f): array {
            $landing = $this->sessions($f)->whereNotNull('landing_path')
                ->selectRaw('landing_path as path, max(landing_title) as title, count(*) as c, avg(pageviews) as pv')
                ->groupBy('landing_path')->orderByDesc('c')->limit(10)->get()
                ->map(fn ($r): array => ['label' => $r->path, 'hint' => $r->title, 'value' => (int) $r->c, 'extra' => round((float) $r->pv, 1)])->all();

            $titles = $this->events($f, 'pageview')->whereNotNull('title')->where('title', '<>', '')
                ->selectRaw('title, max(path) as path, count(*) as c')->groupBy('title')->orderByDesc('c')->limit(10)->get()
                ->map(fn ($r): array => ['label' => $r->title, 'hint' => $r->path, 'value' => (int) $r->c])->all();

            return [
                'types' => $this->top($this->sessions($f), 'source_type'),
                'search' => $this->top($this->sessions($f)->whereNotNull('search_engine'), 'search_engine'),
                'referrers' => $this->top($this->sessions($f)->whereNotNull('referrer_host'), 'referrer_host'),
                'landing' => $landing,
                'titles' => $titles,
                'pages' => $this->top($this->events($f, 'pageview'), 'path'),
            ];
        });
    }

    /** Most common page-to-page transitions (window function; PG, MySQL 8+, MariaDB 10.2+, SQLite 3.25+). */
    public function paths(Filters $f): array
    {
        return $this->remember('paths', $f, function () use ($f): array {
            $bind = [$f->range->from, $f->range->to];
            $where = '';
            if ($f->audience !== Audience::All) {
                $where = ' and is_bot = ?';
                $bind[] = $f->audience === Audience::Bots;
            }
            $sql = "select from_path, path as to_path, count(*) as c from (
                        select path, lag(path) over (partition by sid order by id) as from_path
                        from analytics_events where type = 'pageview' and created_at between ? and ?{$where}
                    ) t where from_path is not null and from_path <> path
                    group by from_path, path order by c desc limit 15";

            return array_map(fn ($r): array => ['label' => $r->from_path, 'hint' => $r->to_path, 'value' => (int) $r->c], $this->conn()->select($sql, $bind));
        });
    }

    // ------------------------------------------------------------------ maps

    /** @return list<array{label: string, value: int}> */
    public function mapPages(Filters $f): array
    {
        return $this->remember('mappages', $f, fn (): array => $this->top($this->events($f, 'pageview'), 'path', 60));
    }

    /** @return array{cells: list<array{0:int,1:int,2:int}>, max: int, total: int, height: int, targets: list<array>} */
    public function clickMap(Filters $f, string $path): array
    {
        return $this->remember('clickmap', $f, function () use ($f, $path): array {
            $base = fn (): Builder => $this->events($f, 'click')->where('path', $path);
            $cells = $base()->whereNotNull('x')->whereNotNull('y')->selectRaw('x, y, count(*) as c')->groupBy('x', 'y')->limit(8000)->get()
                ->map(fn ($r): array => [(int) $r->x, (int) $r->y, (int) $r->c])->all();
            $height = (int) round((float) ($base()->avg('ph') ?? 0));

            $targets = $base()->whereNotNull('target')->selectRaw('target, max(label) as label, count(*) as c')
                ->groupBy('target')->orderByDesc('c')->limit(15)->get()
                ->map(fn ($r): array => ['label' => $r->target, 'hint' => $r->label, 'value' => (int) $r->c])->all();

            return [
                'cells' => $cells, 'max' => max(1, ...array_map(fn ($c) => $c[2], $cells ?: [[0, 0, 1]])),
                'total' => array_sum(array_map(fn ($c) => $c[2], $cells)), 'height' => $height, 'targets' => $targets,
            ];
        }, $path);
    }

    /** @return array{total: int, reach: array<int, float>} reach[10..100] = % of views that scrolled at least that far */
    public function scrollMap(Filters $f, string $path): array
    {
        return $this->remember('scrollmap', $f, function () use ($f, $path): array {
            $cols = ['count(*) as total'];
            foreach (range(10, 100, 10) as $t) {
                $cols[] = "sum(case when value >= {$t} then 1 else 0 end) as d{$t}";
            }
            $r = $this->events($f, 'engage')->where('path', $path)->selectRaw(implode(', ', $cols))->first();
            $total = (int) ($r->total ?? 0);
            $reach = [];
            foreach (range(10, 100, 10) as $t) {
                $reach[$t] = $total > 0 ? round((int) $r->{"d{$t}"} / $total * 100, 1) : 0.0;
            }

            return ['total' => $total, 'reach' => $reach];
        }, $path);
    }

    /** @return array{total: int, bands: list<float>} % of views in which each 5 % vertical band was on screen >= 1 s */
    public function viewMap(Filters $f, string $path): array
    {
        return $this->remember('viewmap', $f, function () use ($f, $path): array {
            $rows = $this->events($f, 'engage')->where('path', $path)->whereNotNull('aux')
                ->selectRaw('aux, count(*) as c')->groupBy('aux')->limit(5000)->get();
            $total = (int) $rows->sum('c');
            $bands = array_fill(0, self::VIEW_BANDS, 0);
            foreach ($rows as $r) {
                for ($i = 0; $i < self::VIEW_BANDS; $i++) {
                    if ((((int) $r->aux) >> $i) & 1) {
                        $bands[$i] += (int) $r->c;
                    }
                }
            }

            return ['total' => $total, 'bands' => array_map(fn (int $b): float => $total > 0 ? round($b / $total * 100, 1) : 0.0, $bands)];
        }, $path);
    }

    /** @return list<array<string, mixed>> */
    public function linkMap(Filters $f, string $path): array
    {
        return $this->remember('linkmap', $f, fn (): array => $this->events($f, 'link')->where('path', $path)->whereNotNull('target')
            ->selectRaw('target, max(label) as label, count(*) as c, count(distinct sid) as s')
            ->groupBy('target')->orderByDesc('c')->limit(20)->get()
            ->map(fn ($r): array => ['label' => $r->target, 'hint' => $r->label, 'value' => (int) $r->c, 'extra' => (int) $r->s])->all(), $path);
    }

    // ------------------------------------------------------------------ forms

    /** @return list<array{form: string, starts: int, submits: int, abandons: int, conversion: float}> */
    public function forms(Filters $f): array
    {
        return $this->remember('forms', $f, function () use ($f): array {
            $rows = $this->events($f)->whereIn('type', ['form_start', 'form_submit', 'form_abandon'])->whereNotNull('target')
                ->selectRaw("target, count(distinct case when type = 'form_start' then sid end) as starts,
                             count(distinct case when type = 'form_submit' then sid end) as submits,
                             count(distinct case when type = 'form_abandon' then sid end) as abandons")
                ->groupBy('target')->orderByDesc('starts')->limit(50)->get();

            return $rows->map(function ($r): array {
                $starts = max((int) $r->starts, (int) $r->submits);

                return ['form' => $r->target, 'starts' => $starts, 'submits' => (int) $r->submits, 'abandons' => (int) $r->abandons,
                    'conversion' => $starts > 0 ? round((int) $r->submits / $starts * 100, 1) : 0.0];
            })->all();
        });
    }

    /** @return list<array{field: string, users: int, abandoned_here: int}> */
    public function formFields(Filters $f, string $form): array
    {
        return $this->remember('formfields', $f, function () use ($f, $form): array {
            $focus = $this->events($f, 'form_field')->where('target', $form)->whereNotNull('label')
                ->selectRaw('label, count(distinct sid) as u')->groupBy('label')->orderByDesc('u')->limit(40)->get();
            $abandon = $this->events($f, 'form_abandon')->where('target', $form)->whereNotNull('label')
                ->selectRaw('label, count(*) as c')->groupBy('label')->pluck('c', 'label');

            return $focus->map(fn ($r): array => ['field' => $r->label, 'users' => (int) $r->u, 'abandoned_here' => (int) ($abandon[$r->label] ?? 0)])->all();
        }, $form);
    }

    // ------------------------------------------------------------------ goals

    /** @return list<array<string, mixed>> */
    public function goals(Filters $f): array
    {
        return $this->remember('goals', $f, function () use ($f): array {
            $total = $this->sessions($f)->count();
            $out = [];
            foreach (Goal::query()->where('is_active', true)->orderBy('id')->get() as $goal) {
                $q = $this->events($f);
                $goal->constrain($q);
                $r = $q->selectRaw('count(distinct sid) as s, count(*) as c, coalesce(sum(value), 0) as v')->first();
                $out[] = [
                    'id' => $goal->id, 'name' => $goal->name, 'type' => $goal->type, 'match' => $goal->match,
                    'sessions' => (int) $r->s, 'hits' => (int) $r->c, 'value' => (int) $r->v,
                    'rate' => $total > 0 ? round((int) $r->s / $total * 100, 2) : 0.0, 'total' => $total,
                ];
            }

            return $out;
        });
    }

    /** Sequential funnel over goals that have a funnel_step. @return list<array<string, mixed>> */
    public function funnel(Filters $f): array
    {
        return $this->remember('funnel', $f, function () use ($f): array {
            $steps = Goal::query()->where('is_active', true)->whereNotNull('funnel_step')->orderBy('funnel_step')->orderBy('id')->get();
            $prevQuery = null;
            $prevCount = null;
            $first = null;
            $out = [];
            foreach ($steps as $goal) {
                $q = $this->events($f)->select('sid')->distinct();
                $goal->constrain($q);
                if ($prevQuery !== null) {
                    $q->whereIn('sid', $prevQuery);
                }
                $count = (int) $this->conn()->query()->fromSub($q, 't')->count();
                $first ??= $count;
                $out[] = [
                    'name' => $goal->name, 'step' => (int) $goal->funnel_step, 'count' => $count,
                    'of_first' => $first > 0 ? round($count / $first * 100, 1) : 0.0,
                    'of_prev' => $prevCount !== null && $prevCount > 0 ? round($count / $prevCount * 100, 1) : null,
                ];
                $prevQuery = $q;
                $prevCount = $count;
            }

            return $out;
        });
    }
}
