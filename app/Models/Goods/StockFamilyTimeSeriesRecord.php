<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 12:22:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockFamilyTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockFamilyTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockFamilyTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class StockFamilyTimeSeriesRecord extends Model
{
    protected $table = 'stock_family_time_series_records';

    protected $guarded = [];
}
