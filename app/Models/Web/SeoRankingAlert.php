<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tracked keyword that fell at a Google check: out of the top 10, out of the results read, or down
 * `DROP_PLACES` places or more. Written for every keyword; only the people watching it are told.
 *
 * @property int $id
 * @property int $tracked_keyword_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $reason
 * @property int|null $previous_position
 * @property int|null $position
 * @property \Illuminate\Support\Carbon|null $notified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read SeoTrackedKeyword $trackedKeyword
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoRankingAlert newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoRankingAlert newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoRankingAlert query()
 * @mixin \Eloquent
 */
class SeoRankingAlert extends Model
{
    public const int DROP_PLACES = 5;

    public const string REASON_LEFT_TOP_10 = 'left_top_10';

    public const string REASON_LOST = 'lost';

    public const string REASON_DROPPED = 'dropped';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'        => 'date',
            'notified_at' => 'datetime',
        ];
    }

    public static function reason(?int $previous, ?int $position): ?string
    {
        if ($previous !== null && $previous <= 10 && ($position === null || $position > 10)) {
            return self::REASON_LEFT_TOP_10;
        }

        if ($previous !== null && $position === null) {
            return self::REASON_LOST;
        }

        if ($previous !== null && $position - $previous >= self::DROP_PLACES) {
            return self::REASON_DROPPED;
        }

        return null;
    }

    public function trackedKeyword(): BelongsTo
    {
        return $this->belongsTo(SeoTrackedKeyword::class, 'tracked_keyword_id');
    }
}
