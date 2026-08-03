<?php

namespace App\Models\Reviews;

use App\Models\SysAdmin\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $number_reviews
 * @property int $number_reviews_pending
 * @property int $number_reviews_approved
 * @property int $number_reviews_rejected
 * @property int $number_rating_1
 * @property int $number_rating_2
 * @property int $number_rating_3
 * @property int $number_rating_4
 * @property int $number_rating_5
 * @property numeric $average_rating_main
 * @property numeric $average_rating_a
 * @property numeric $average_rating_b
 * @property numeric $average_rating_c
 * @property numeric $average_rating_d
 * @property numeric $average_rating_e
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group|null $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupReviewStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupReviewStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupReviewStat query()
 * @mixin \Eloquent
 */
class GroupReviewStat extends Model
{
    protected $table = 'group_review_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
