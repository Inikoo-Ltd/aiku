<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 03:13:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class ShopTimeSeriesRecord extends Model
{
    protected $table = 'shop_time_series_records';

    protected $guarded = [];



}
