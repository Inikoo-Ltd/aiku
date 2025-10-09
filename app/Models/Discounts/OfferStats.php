<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 20:14:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Discounts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $offer_id
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
 * @property-read \App\Models\Discounts\Offer $offer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferStats query()
 * @mixin \Eloquent
 */
class OfferStats extends Model
{
    protected $table = 'offer_stats';

    protected $guarded = [];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
