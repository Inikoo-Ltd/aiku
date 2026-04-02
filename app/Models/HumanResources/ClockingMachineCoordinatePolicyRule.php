<?php

namespace App\Models\HumanResources;

use App\Enums\HumanResources\ClockingMachine\ClockingPolicyModeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $clocking_machine_coordinate_policy_id
 * @property int|null $day_of_week
 * @property ClockingPolicyModeEnum $mode_override
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\HumanResources\ClockingMachineCoordinatePolicy $policy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClockingMachineCoordinatePolicyRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClockingMachineCoordinatePolicyRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClockingMachineCoordinatePolicyRule query()
 * @mixin \Eloquent
 */
class ClockingMachineCoordinatePolicyRule extends Model
{
    protected $guarded = [];
    protected $casts = [
        'mode_override' => ClockingPolicyModeEnum::class,
        'is_active'     => 'boolean',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(ClockingMachineCoordinatePolicy::class, 'clocking_machine_coordinate_policy_id');
    }
}
