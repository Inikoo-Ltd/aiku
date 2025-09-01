<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 03:19:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class GroupTimeSeriesRecord extends Model
{
    protected $table = 'group_time_series_records';

    protected $guarded = [];



}
