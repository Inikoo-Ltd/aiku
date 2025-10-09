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
 * @property int $offer_component_id
 * @property string $discounted_amount
 * @property string $discounted_percentage
 * @property string $free_items_value
 * @property string $number_of_free_items
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
 * @property-read \App\Models\Discounts\Offer $offer
 * @property-read \App\Models\Discounts\OfferCampaign $offerCampaign
 * @property-read \App\Models\Discounts\OfferComponent $offerComponent
 * @property-read Transaction|null $transaction
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionHasOfferComponent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionHasOfferComponent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionHasOfferComponent query()
 * @mixin \Eloquent
 */
class TransactionHasOfferComponent extends Model
{
    protected $table = 'transaction_has_offer_components';

    protected $casts = [
        'data'            => 'array',
        'is_pinned'       => 'boolean',
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

    public function offerComponent(): BelongsTo
    {
        return $this->belongsTo(OfferComponent::class);
    }

}
