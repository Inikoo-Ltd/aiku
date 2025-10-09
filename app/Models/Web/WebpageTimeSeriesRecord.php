<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 15:54:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebpageTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebpageTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebpageTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class WebpageTimeSeriesRecord extends Model
{
    protected $table = 'webpage_time_series_records';

    protected $guarded = [];



}
