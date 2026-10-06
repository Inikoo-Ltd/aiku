<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 02 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Discounts;

use App\Models\CRM\Customer;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shop_id
 * @property int $offer_id
 * @property int $customer_id
 * @property string|null $code
 * @property string|null $voucher
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read \App\Models\Discounts\Offer $offer
 * @method static Builder<static>|OfferHasCustomer newModelQuery()
 * @method static Builder<static>|OfferHasCustomer newQuery()
 * @method static Builder<static>|OfferHasCustomer query()
 * @mixin Eloquent
 */
class OfferHasCustomer extends Model
{
    protected $guarded = [];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
