<?php

namespace App\Models\HumanResources\Overtime;

use App\Enums\HumanResources\Overtime\OvertimeAllowanceUnitEnum;
use App\Models\HumanResources\Employee;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $employee_id
 * @property \Illuminate\Support\Carbon $period_start_date
 * @property \Illuminate\Support\Carbon $period_end_date
 * @property int $opening_minutes
 * @property int $booked_minutes
 * @property int $remaining_minutes
 * @property OvertimeAllowanceUnitEnum $unit
 * @property string|null $notes
 */
class EmployeeOvertimeAllowance extends Model
{
    use InOrganisation;

    protected $guarded = [];

    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date'   => 'date',
        'opening_minutes'   => 'integer',
        'booked_minutes'    => 'integer',
        'remaining_minutes' => 'integer',
        'unit'              => OvertimeAllowanceUnitEnum::class,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
