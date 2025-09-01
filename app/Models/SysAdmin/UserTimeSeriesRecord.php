<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 15:46:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class UserTimeSeriesRecord extends Model
{
    protected $table = 'user_time_series_records';

    protected $guarded = [];



}
