<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 16:01:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Discounts;

use App\Models\Ordering\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $transaction_id
 * @property string|null $model_type
 * @property int|null $model_id
 * @property int $offer_campaign_id
 * @property int $offer_id
 * @property int $offer_allowance_id
 * @property numeric $discounted_amount
 * @property numeric $discounted_percentage
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
 * @property string|null $source_alt_id
 * @property bool $is_gift
 * @property-read \App\Models\Discounts\Offer|null $offer
 * @property-read \App\Models\Discounts\OfferAllowance|null $offerAllowance
 * @property-read \App\Models\Discounts\OfferCampaign|null $offerCampaign
 * @property-read Transaction|null $transaction
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionHasOfferAllowance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionHasOfferAllowance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionHasOfferAllowance query()
 * @mixin \Eloquent
 */
class TransactionHasOfferAllowance extends Model
{
    protected $table = 'transaction_has_offer_allowances';

    protected $casts = [
        'data'            => 'array',
        'is_pinned'       => 'boolean',
        'is_gift'         => 'boolean',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
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
