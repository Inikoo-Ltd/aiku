<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 03:27:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class OrganisationTimeSeriesRecord extends Model
{
    protected $table = 'organisation_time_series_records';

    protected $guarded = [];



}
