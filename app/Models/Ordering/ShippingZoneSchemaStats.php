<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Sept 2024 13:50:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Ordering;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shipping_zone_schema_id
 * @property string|null $first_used_at
 * @property string|null $last_used_at
 * @property int $number_shipping_zones
 * @property int $number_customers
 * @property int $number_orders
 * @property string $amount
 * @property string $org_amount
 * @property string $grp_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Ordering\ShippingZoneSchema $shippingZoneSchema
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShippingZoneSchemaStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShippingZoneSchemaStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShippingZoneSchemaStats query()
 * @mixin \Eloquent
 */
class ShippingZoneSchemaStats extends Model
{
    protected $table = 'shipping_zone_schema_stats';

    protected $guarded = [];

    public function shippingZoneSchema(): BelongsTo
    {
        return $this->belongsTo(ShippingZoneSchema::class);
    }
}
