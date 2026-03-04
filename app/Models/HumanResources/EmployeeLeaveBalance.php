<?php

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $year
 * @property int $annual_days
 * @property float $annual_used
 * @property int $unpaid_days
 * @property float $unpaid_used
 * @property int $medical_days
 * @property float $medical_used
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\HumanResources\Employee $employee
 * @property-read float $annual_remaining
 * @property-read float $unpaid_remaining
 * @property-read float $medical_remaining
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeLeaveBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeLeaveBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeLeaveBalance query()
 * @mixin \Eloquent
 */
class EmployeeLeaveBalance extends Model
{
    protected $guarded = [];

    protected $casts = [
        'annual_used' => 'float',
        'medical_used' => 'float',
        'unpaid_used' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getAnnualRemainingAttribute(): float
    {
        return max(0, $this->annual_days - $this->annual_used);
    }

    public function getUnpaidRemainingAttribute(): float
    {
        return max(0, $this->unpaid_days - $this->unpaid_used);
    }

    public function getMedicalRemainingAttribute(): float
    {
        return max(0, $this->medical_days - $this->medical_used);
    }
}
