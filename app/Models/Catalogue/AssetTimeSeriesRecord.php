<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 14:22:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class AssetTimeSeriesRecord extends Model
{
    protected $table = 'asset_time_series_records';

    protected $guarded = [];
}
