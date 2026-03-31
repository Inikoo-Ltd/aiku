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
 * @property int $organisation_stock_history_id
 * @property int $organisation_id
 * @property int $org_stock_id
 * @property \Illuminate\Support\Carbon $date
 * @property float $quantity_in_locations Stock at the end of the day, min value zero
 * @property string $org_stock_value FIFO method
 * @property numeric $grp_stock_value FIFO method
 * @property string $org_stock_commercial_value
 * @property numeric $grp_stock_commercial_value
 * @property int $number_locations
 * @property float|null $value_per_sku
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Inventory\OrgStock $orgStock
 * @property-read \App\Models\Inventory\Warehouse|null $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockHistory query()
 * @mixin \Eloquent
 */
class OrgStockHistory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'                           => 'date',
            'stock_value'                    => 'decimal:2',
            'grp_stock_value'                => 'decimal:2',
            'stock_commercial_value'         => 'decimal:2',
            'grp_stock_commercial_value'     => 'decimal:2',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }
}
