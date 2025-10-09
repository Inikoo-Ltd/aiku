<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 09:31:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $clocking_machine_id
 * @property string|null $last_clocking_at
 * @property int $number_clockings
 * @property int $number_clockings_type_clocking_machine
 * @property int $number_clockings_type_manual
 * @property int $number_clockings_type_self_check
 * @property int $number_clockings_type_system
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\HumanResources\ClockingMachine|null $clockingMachine
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClockingMachineStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClockingMachineStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClockingMachineStats query()
 * @mixin \Eloquent
 */
class ClockingMachineStats extends Model
{
    protected $table = 'clocking_machine_stats';

    protected $guarded = [];

    public function clockingMachine(): BelongsTo
    {
        return $this->belongsTo(ClockingMachine::class);
    }

}
