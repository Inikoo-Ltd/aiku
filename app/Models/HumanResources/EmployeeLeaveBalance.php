<?php

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $year
 * @property int $annual_days
 * @property int $annual_used
 * @property int $medical_days
 * @property int $medical_used
 * @property int $unpaid_days
 * @property int $unpaid_used
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\HumanResources\Employee $employee
 * @property-read int $annual_remaining
 * @property-read int $medical_remaining
 * @property-read int $unpaid_remaining
 */
class EmployeeLeaveBalance extends Model
{
    protected $guarded = [];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getAnnualRemainingAttribute(): int
    {
        return max(0, $this->annual_days - $this->annual_used);
    }

    public function getMedicalRemainingAttribute(): int
    {
        return max(0, $this->medical_days - $this->medical_used);
    }

    public function getUnpaidRemainingAttribute(): int
    {
        return max(0, $this->unpaid_days - $this->unpaid_used);
    }
}
