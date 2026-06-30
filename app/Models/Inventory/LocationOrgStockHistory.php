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
 * @property-read \App\Models\Inventory\Location|null $location
 * @property-read \App\Models\Inventory\OrgStock|null $orgStock
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
