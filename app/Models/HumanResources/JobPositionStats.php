<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 May 2024 13:09:03 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $job_position_id
 * @property int $number_employees
 * @property int $number_employees_currently_working
 * @property int $number_employees_state_hired
 * @property int $number_employees_state_working
 * @property int $number_employees_state_leaving
 * @property int $number_employees_state_left
 * @property int $number_employees_type_employee
 * @property int $number_employees_type_volunteer
 * @property int $number_employees_type_temporal_worker
 * @property int $number_employees_type_work_experience
 * @property int $number_employees_gender_male
 * @property int $number_employees_gender_female
 * @property int $number_employees_gender_other
 * @property int $number_guests
 * @property int $number_guests_status_active
 * @property int $number_guests_status_inactive
 * @property int $number_roles
 * @property float $number_employees_work_time
 * @property float $number_guests_work_time
 * @property string|null $share_work_time This is the share of the total work time of the employees in this job position
 * @property string|null $share_work_time_including_guests This is the share of the total work time of the employees and guests in this job position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositionStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositionStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobPositionStats query()
 * @mixin \Eloquent
 */
class JobPositionStats extends Model
{
    protected $guarded = [];
}
