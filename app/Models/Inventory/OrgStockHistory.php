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
 * @property-read \App\Models\Inventory\OrgStock|null $orgStock
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
            'date'                        => 'date',
            'org_stock_value'             => 'decimal:2',
            'grp_stock_value'             => 'decimal:2',
            'org_stock_commercial_value'  => 'decimal:2',
            'grp_stock_commercial_value'  => 'decimal:2',
            'sold_1y'                     => 'boolean',
            'last_sold_date'              => 'date',
            'non_moving_1y'               => 'double',
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
