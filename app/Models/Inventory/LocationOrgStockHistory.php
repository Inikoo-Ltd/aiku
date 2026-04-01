<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Mar 2026 18:54:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $org_stock_history_id
 * @property int $org_stock_id
 * @property int $location_id
 * @property \Illuminate\Support\Carbon $date
 * @property float $actual_quantity_in_locations Stock at en of day, allow negative values
 * @property float $quantity_in_locations Stock at the end of the day, min value zero
 * @property string $org_stock_value
 * @property string $grp_stock_value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Inventory\Location $location
 * @property-read \App\Models\Inventory\OrgStock $orgStock
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocationOrgStockHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocationOrgStockHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocationOrgStockHistory query()
 * @mixin \Eloquent
 */
class LocationOrgStockHistory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'            => 'date',
            'org_stock_value' => 'decimal:2',
            'grp_stock_value' => 'decimal:2',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }
}
