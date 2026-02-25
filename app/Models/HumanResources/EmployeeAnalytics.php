<?php

namespace App\Models\HumanResources;

use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $employee_id
 * @property \Illuminate\Support\Carbon $period_start
 * @property \Illuminate\Support\Carbon $period_end
 * @property int $working_days
 * @property int $present_days
 * @property int $absent_days
 * @property int $late_clockins
 * @property int $early_clockouts
 * @property numeric $total_working_hours
 * @property numeric $overtime_hours
 * @property int $total_leave_days
 * @property array $leave_breakdown
 * @property numeric $attendance_percentage
 * @property numeric $avg_daily_hours
 * @property numeric $overtime_ratio
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\HumanResources\Employee $employee
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 */
class EmployeeAnalytics extends Model
{
    use InOrganisation;

    protected $casts = [
        'period_start'         => 'date',
        'period_end'           => 'date',
        'total_working_hours'  => 'decimal:2',
        'overtime_hours'       => 'decimal:2',
        'leave_breakdown'      => 'array',
        'attendance_percentage'=> 'decimal:2',
        'avg_daily_hours'      => 'decimal:2',
        'overtime_ratio'       => 'decimal:2',
        'data'                 => 'array',
    ];

    protected $guarded = [];

    protected $attributes = [
        'leave_breakdown' => '{}',
        'data'            => '{}',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
