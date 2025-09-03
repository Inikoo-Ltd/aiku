<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 Nov 2024 12:20:49 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Discounts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $offer_component_id
 * @property string|null $first_used_at
 * @property string|null $last_used_at
 * @property int $number_customers
 * @property int $number_orders
 * @property int $number_invoices
 * @property int $number_delivery_notes
 * @property string $amount
 * @property string $org_amount
 * @property string $grp_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Discounts\OfferComponent $offerComponent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferComponentStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferComponentStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferComponentStats query()
 * @mixin \Eloquent
 */
class OfferComponentStats extends Model
{
    protected $table = 'offer_component_stats';

    protected $guarded = [];

    public function offerComponent(): BelongsTo
    {
        return $this->belongsTo(OfferComponent::class);
    }
}
