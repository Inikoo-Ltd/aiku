<?php

namespace App\Models\HumanResources;

use App\Enums\HumanResources\ClockingMachine\ClockingPolicyModeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $organisation_id
 * @property string $scope_type
 * @property int $scope_id
 * @property int|null $clocking_machine_id
 * @property ClockingPolicyModeEnum $mode
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $start_at
 * @property \Illuminate\Support\Carbon|null $end_at
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\HumanResources\ClockingMachine|null $clockingMachine
 * @property-read Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HumanResources\ClockingMachineCoordinatePolicyRule> $rules
 * @property-read Model|\Eloquent $scope
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClockingMachineCoordinatePolicy newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClockingMachineCoordinatePolicy newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClockingMachineCoordinatePolicy query()
 * @mixin \Eloquent
 */
class ClockingMachineCoordinatePolicy extends Model
{
    protected $guarded = [];

    protected $casts = [
        'mode'      => ClockingPolicyModeEnum::class,
        'is_active' => 'boolean',
        'start_at'  => 'datetime',
        'end_at'    => 'datetime',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function clockingMachine(): BelongsTo
    {
        return $this->belongsTo(ClockingMachine::class);
    }

    public function scope(): MorphTo
    {
        return $this->morphTo();
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ClockingMachineCoordinatePolicyRule::class);
    }
}
