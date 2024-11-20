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
 *
 *
 * @property int $id
 * @property int $transaction_id
 * @property int $offer_campaign_id
 * @property int $offer_id
 * @property int $offer_component_id
 * @property string $discounted_amount
 * @property string $discounted_percentage
 * @property string|null $info
 * @property bool $is_pinned
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property string|null $alt_source_id
 * @property string|null $deleted_at
 * @property-read \App\Models\Discounts\Offer $offer
 * @property-read \App\Models\Discounts\OfferCampaign $offerCampaign
 * @property-read \App\Models\Discounts\OfferComponent $offerComponent
 * @property-read Transaction $transaction
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

    public function offerComponent(): BelongsTo
    {
        return $this->belongsTo(OfferComponent::class);
    }

    public function offerCampaign(): BelongsTo
    {
        return $this->belongsTo(OfferCampaign::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

}
