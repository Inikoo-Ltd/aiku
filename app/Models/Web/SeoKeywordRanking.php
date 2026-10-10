<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Google check of a tracked keyword. `position` is the organic position of our website, null
 * when it is not within the `depth` results checked.
 *
 * @property int $id
 * @property int $tracked_keyword_id
 * @property \Illuminate\Support\Carbon $date
 * @property int|null $position
 * @property string|null $ranking_url
 * @property int|null $webpage_id
 * @property array<array-key, mixed>|null $serp_features
 * @property bool $in_ai_overview
 * @property int $depth
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read SeoTrackedKeyword $trackedKeyword
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoKeywordRanking newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoKeywordRanking newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoKeywordRanking query()
 * @mixin \Eloquent
 */
class SeoKeywordRanking extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'           => 'date',
            'serp_features'  => 'array',
            'in_ai_overview' => 'boolean',
        ];
    }

    public function trackedKeyword(): BelongsTo
    {
        return $this->belongsTo(SeoTrackedKeyword::class, 'tracked_keyword_id');
    }
}
