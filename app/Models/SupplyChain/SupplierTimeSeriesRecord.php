<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 15:43:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class SupplierTimeSeriesRecord extends Model
{
    protected $table = 'supplier_time_series_records';

    protected $guarded = [];



}
