<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Nov 2024 10:34:49 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Discounts;

use App\Models\Accounting\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string|null $model_type
 * @property int|null $model_id
 * @property int $offer_campaign_id
 * @property int $offer_id
 * @property int $offer_component_id
 * @property string $discounted_amount
 * @property string|null $discounted_percentage
 * @property string $free_items_value
 * @property string $number_of_free_items
 * @property string|null $info
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property-read Invoice $invoice
 * @property-read \App\Models\Discounts\Offer $offer
 * @property-read \App\Models\Discounts\OfferCampaign $offerCampaign
 * @property-read \App\Models\Discounts\OfferComponent $offerComponent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceHasNoInvoiceTransactionOfferComponent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceHasNoInvoiceTransactionOfferComponent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceHasNoInvoiceTransactionOfferComponent query()
 * @mixin \Eloquent
 */
class InvoiceHasNoInvoiceTransactionOfferComponent extends Model
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
