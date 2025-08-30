<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Apr 2024 16:22:08 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $guest_id
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestStats query()
 * @mixin \Eloquent
 */
class GuestStats extends Model
{
    protected $guarded = [];

}
