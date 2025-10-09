<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 14:25:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WarehouseTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WarehouseTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WarehouseTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class WarehouseTimeSeriesRecord extends Model
{
    protected $table = 'warehouse_time_series_records';

    protected $guarded = [];



}
