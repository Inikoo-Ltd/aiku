<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property int $annual_leave_days
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read EmployeeLeaveBalance|null $leaveBalance
 */
class EmployeeContract extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date'        => 'date',
        'end_date'          => 'date',
        'annual_leave_days' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveBalance(): HasOne
    {
        return $this->hasOne(EmployeeLeaveBalance::class);
    }

    public function isActive(): bool
    {
        return $this->end_date === null || $this->end_date->isFuture();
    }

    public function coversPeriod(Carbon $date): bool
    {
        if ($date->lt($this->start_date)) {
            return false;
        }

        return $this->end_date === null || $date->lte($this->end_date);
    }
}
