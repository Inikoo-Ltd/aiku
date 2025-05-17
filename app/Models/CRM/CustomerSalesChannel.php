<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 17:15:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use App\Models\Dropshipping\Platform;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int|null $organisation_id
 * @property int $shop_id
 * @property int $platform_id
 * @property string|null $reference
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $customer_id
 * @property int $number_customer_clients
 * @property int $number_portfolios
 * @property string|null $last_order_created_at
 * @property string|null $last_order_submitted_at
 * @property string|null $last_order_dispatched_at
 * @property int $number_orders
 * @property int $number_orders_state_creating
 * @property int $number_orders_state_submitted
 * @property int $number_orders_state_in_warehouse
 * @property int $number_orders_state_handling
 * @property int $number_orders_state_handling_blocked
 * @property int $number_orders_state_packed
 * @property int $number_orders_state_finalised
 * @property int $number_orders_state_dispatched
 * @property int $number_orders_state_cancelled
 * @property int $number_orders_status_creating
 * @property int $number_orders_status_processing
 * @property int $number_orders_status_settled
 * @property int $number_orders_handing_type_collection
 * @property int $number_orders_handing_type_shipping
 * @property-read \App\Models\CRM\Customer|null $customer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read Platform $platform
 * @property-read \App\Models\Catalogue\Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSalesChannel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSalesChannel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSalesChannel query()
 * @mixin \Eloquent
 */
class CustomerSalesChannel extends Model
{
    use InShop;

    protected $table = 'customer_has_platforms';

    protected $guarded = [];

    protected $casts = [
        'data' => 'array'
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}
