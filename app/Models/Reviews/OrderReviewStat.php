<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 12:44:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Reviews;

use App\Models\Ordering\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
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
 * @property-read Order|null $order
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderReviewStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderReviewStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderReviewStat query()
 * @mixin \Eloquent
 */
class OrderReviewStat extends Model
{
    protected $table = 'order_review_stats';

    protected $guarded = [];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
