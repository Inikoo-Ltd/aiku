<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Jun 2025 19:58:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Models\Traits\InWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $date
 * @property int $group_id
 * @property int $organisation_id
 * @property int $warehouse_id
 * @property int $org_stock_id
 * @property float $actual_quantity_in_locations Stock at en of day , allow negative values
 * @property float $quantity_in_locations Stock at end of day, min value zero
 * @property float $unit_value
 * @property float $value_in_locations Stock value at end of day, (unit_value*quantity_in_locations) organisation currency
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Inventory\OrgStock $orgStock
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Inventory\Warehouse $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryDailySnapshot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryDailySnapshot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryDailySnapshot query()
 * @mixin \Eloquent
 */
class InventoryDailySnapshot extends Model
{
    use InWarehouse;

    protected $casts = [
        'data' => 'array',
        'date' => 'date',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

}
