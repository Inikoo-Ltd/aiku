<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Sep 2023 16:06:07 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\SysAdmin\OrganisationHumanResourcesStats
 *
 * @property int $id
 * @property int $group_id
 * @property int $number_job_positions
 * @property int $number_workplaces
 * @property int $number_workplaces_type_hq
 * @property int $number_workplaces_type_branch
 * @property int $number_workplaces_type_home
 * @property int $number_workplaces_type_group_premisses
 * @property int $number_workplaces_type_client_premises
 * @property int $number_workplaces_type_road
 * @property int $number_workplaces_type_other
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
 * @property int $number_clocking_machines
 * @property int $number_clocking_machines_type_biometric
 * @property int $number_clocking_machines_type_static_nfc
 * @property int $number_clocking_machines_type_mobile_app
 * @property int $number_clocking_machines_type_legacy
 * @property int $number_clocking_machines_status_disconnected
 * @property int $number_clocking_machines_status_connected
 * @property int $number_clocking_machines_status_decommissioned
 * @property string|null $last_clocking_at
 * @property int $number_clockings
 * @property int $number_clockings_type_clocking_machine
 * @property int $number_clockings_type_manual
 * @property int $number_clockings_type_self_check
 * @property int $number_clockings_type_system
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupHumanResourcesStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupHumanResourcesStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupHumanResourcesStats query()
 * @mixin \Eloquent
 */
class GroupHumanResourcesStats extends Model
{
    protected $table = 'group_human_resources_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
