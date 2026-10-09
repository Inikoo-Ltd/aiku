<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A competitor's organic position for a tracked keyword, read from the same Google check as ours.
 *
 * @property int $id
 * @property int $tracked_keyword_id
 * @property int $competitor_id
 * @property \Illuminate\Support\Carbon $date
 * @property int|null $position
 * @property string|null $url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read SeoCompetitor $competitor
 * @property-read SeoTrackedKeyword $trackedKeyword
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoCompetitorRanking newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoCompetitorRanking newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoCompetitorRanking query()
 * @mixin \Eloquent
 */
class SeoCompetitorRanking extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(SeoCompetitor::class, 'competitor_id');
    }

    public function trackedKeyword(): BelongsTo
    {
        return $this->belongsTo(SeoTrackedKeyword::class, 'tracked_keyword_id');
    }
}
