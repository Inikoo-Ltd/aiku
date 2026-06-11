<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: YYYY-MM-DD HH:mm:ss
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int|null $employee_contract_id
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property int $annual_days
 * @property float $annual_used
 * @property float $medical_used
 * @property float $unpaid_used
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\HumanResources\Employee|null $employee
 * @property-read float $annual_remaining
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeLeaveBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeLeaveBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeLeaveBalance query()
 * @mixin \Eloquent
 */
class EmployeeLeaveBalance extends Model
{
    protected $guarded = [];

    protected $casts = [
        'annual_used'  => 'float',
        'medical_used' => 'float',
        'unpaid_used'  => 'float',
        'period_start' => 'date',
        'period_end'   => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(EmployeeContract::class, 'employee_contract_id');
    }

    public function getAnnualRemainingAttribute(): float
    {
        return max(0, $this->annual_days - $this->annual_used);
    }
}
