<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 15:53:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class WebsiteTimeSeriesRecord extends Model
{
    protected $table = 'website_time_series_records';

    protected $guarded = [];



}
