<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Apr 2025 16:11:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Goods\StockFamily|null $stockFamily
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockFamilySalesIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockFamilySalesIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockFamilySalesIntervals query()
 * @mixin \Eloquent
 */
class StockFamilySalesIntervals extends Model
{
    protected $table = 'stock_family_sales_intervals';

    protected $guarded = [];


    public function stockFamily(): BelongsTo
    {
        return $this->belongsTo(StockFamily::class);
    }
}
