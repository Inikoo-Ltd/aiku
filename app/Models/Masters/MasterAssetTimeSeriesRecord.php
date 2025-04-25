<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Dec 2024 12:49:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAssetTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAssetTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAssetTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class MasterAssetTimeSeriesRecord extends Model
{
    protected $table = 'master_asset_time_series_records';

    protected $guarded = [];
}
