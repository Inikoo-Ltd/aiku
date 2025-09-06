<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 00:55:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class StockTimeSeriesRecord extends Model
{
    protected $table = 'stock_time_series_records';

    protected $guarded = [];
}
