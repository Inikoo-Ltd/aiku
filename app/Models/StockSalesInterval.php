<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Apr 2025 15:33:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models;

use App\Models\Goods\Stock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Stock|null $stock
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockSalesInterval newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockSalesInterval newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockSalesInterval query()
 * @mixin \Eloquent
 */
class StockSalesInterval extends Model
{
    protected $table = 'stock_sales_intervals';

    protected $guarded = [];


    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
