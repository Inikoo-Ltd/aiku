<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Nov 2024 10:36:52 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Discounts;

use App\Models\Ordering\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property string|null $model_type
 * @property int|null $model_id
 * @property int $offer_campaign_id
 * @property int $offer_id
 * @property int $offer_allowance_id
 * @property numeric $discounted_amount
 * @property numeric|null $discounted_percentage
 * @property numeric $free_items_value
 * @property numeric $number_of_free_items
 * @property string|null $info
 * @property bool $is_pinned
 * @property string|null $precursor
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property-read \App\Models\Discounts\Offer|null $offer
 * @property-read \App\Models\Discounts\OfferAllowance|null $offerAllowance
 * @property-read \App\Models\Discounts\OfferCampaign|null $offerCampaign
 * @property-read Order|null $order
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHasNoTransactionOfferAllowance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHasNoTransactionOfferAllowance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderHasNoTransactionOfferAllowance query()
 * @mixin \Eloquent
 */
class OrderHasNoTransactionOfferAllowance extends Model
{
    protected $casts = [
        'data'            => 'array',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function offerCampaign(): BelongsTo
    {
        return $this->belongsTo(OfferCampaign::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function offerAllowance(): BelongsTo
    {
        return $this->belongsTo(OfferAllowance::class);
    }

}
