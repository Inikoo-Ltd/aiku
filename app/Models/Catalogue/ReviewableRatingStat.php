<?php

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $reviewable_type
 * @property int $reviewable_id
 * @property int $number_reviews_state_pending
 * @property int $number_reviews_state_approved
 * @property int $number_reviews_state_rejected
 * @property int $number_reviews_rating_1
 * @property int $number_reviews_rating_2
 * @property int $number_reviews_rating_3
 * @property int $number_reviews_rating_4
 * @property int $number_reviews_rating_5
 * @property int $reviews_count
 * @property int $verified_reviews_count
 * @property numeric $rating_average
 * @property array<array-key, mixed> $rating_breakdown
 * @property \Illuminate\Support\Carbon|null $last_reviewed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $reviewable
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewableRatingStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewableRatingStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewableRatingStat query()
 * @mixin \Eloquent
 */
class ReviewableRatingStat extends Model
{
    protected $guarded = [];

    protected $casts = [
        'reviews_count'          => 'integer',
        'rating_average'         => 'decimal:2',
        'rating_breakdown'       => 'array',
        'last_reviewed_at'       => 'datetime',
        'number_reviews_state_pending'  => 'integer',
        'number_reviews_state_approved' => 'integer',
        'number_reviews_state_rejected' => 'integer',
        'number_reviews_rating_1' => 'integer',
        'number_reviews_rating_2' => 'integer',
        'number_reviews_rating_3' => 'integer',
        'number_reviews_rating_4' => 'integer',
        'number_reviews_rating_5' => 'integer',
        'number_reviews_like'     => 'integer',
    ];

    protected $attributes = [
        'rating_breakdown' => '{}',
    ];

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }
}
