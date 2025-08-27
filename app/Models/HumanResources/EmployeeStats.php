<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Apr 2024 16:23:02 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $employee_id
 * @property int $number_job_positions
 * @property int $number_timesheets
 * @property string|null $last_clocking_at
 * @property int $number_clockings
 * @property int $number_clockings_type_clocking_machine
 * @property int $number_clockings_type_manual
 * @property int $number_clockings_type_self_check
 * @property int $number_clockings_type_system
 * @property int $number_time_trackers
 * @property int $number_time_trackers_status_open
 * @property int $number_time_trackers_status_closed
 * @property int $number_time_trackers_status_error
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeStats query()
 * @mixin \Eloquent
 */
class EmployeeStats extends Model
{
    protected $guarded = [];

}
