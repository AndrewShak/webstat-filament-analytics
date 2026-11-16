<?php

declare(strict_types=1);

namespace Webstat\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Webstat\Analytics\Support\LikePattern;

/**
 * @property int $id
 * @property string $name
 * @property string $type   path|event|click|form
 * @property string $match
 * @property int|null $funnel_step
 * @property bool $is_active
 */
class Goal extends Model
{
    public const TYPES = ['path', 'event', 'click', 'form'];

    protected $table = 'analytics_goals';

    protected $fillable = ['name', 'type', 'match', 'funnel_step', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'funnel_step' => 'integer'];

    public function getConnectionName(): ?string
    {
        return config('analytics.connection');
    }

    /** Restrict an events query to rows that satisfy this goal. */
    public function constrain(Builder $q): Builder
    {
        return match ($this->type) {
            'path' => $q->where('type', 'pageview')
                ->whereRaw("path like ? escape '!'", [LikePattern::wildcard($this->match)]),
            'event' => $q->where('type', 'goal')->where('target', $this->match),
            'click' => $q->whereIn('type', ['click', 'link'])
                ->whereRaw("target like ? escape '!'", [LikePattern::contains($this->match)]),
            'form' => $q->where('type', 'form_submit')
                ->whereRaw("target like ? escape '!'", [LikePattern::contains($this->match)]),
            default => $q->whereRaw('1 = 0'),
        };
    }
}
