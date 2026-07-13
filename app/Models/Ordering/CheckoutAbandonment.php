<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 08 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Ordering;

use App\Enums\Ordering\CheckoutAbandonment\CheckoutAbandonmentStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $order_id
 * @property int $customer_id
 * @property \Illuminate\Support\Carbon $checkout_visited_at
 * @property numeric $total_amount
 * @property CheckoutAbandonmentStateEnum $state
 * @property \Illuminate\Support\Carbon|null $recovered_at
 * @property \Illuminate\Support\Carbon|null $email_sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read Group|null $group
 * @property-read \App\Models\Ordering\Order|null $order
 * @property-read Organisation $organisation
 * @property-read Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CheckoutAbandonment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CheckoutAbandonment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CheckoutAbandonment query()
 * @mixin \Eloquent
 */
class CheckoutAbandonment extends Model
{
    use InCustomer;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'checkout_visited_at' => 'datetime',
            'recovered_at'        => 'datetime',
            'email_sent_at'       => 'datetime',
            'total_amount'        => 'decimal:2',
            'state'               => CheckoutAbandonmentStateEnum::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
