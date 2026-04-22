<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 11:57:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Billables;

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
 * @property numeric $amount
 * @property numeric $org_amount
 * @property numeric $grp_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Billables\ShippingZoneSchema|null $shippingZoneSchema
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
