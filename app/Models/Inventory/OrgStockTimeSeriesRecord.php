<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 13:25:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class OrgStockTimeSeriesRecord extends Model
{
    protected $table = 'org_stock_time_series_records';

    protected $guarded = [];
}
