<?php

namespace App\Models\HumanResources\Overtime;

use App\Enums\HumanResources\Overtime\OvertimeApproverDecisionEnum;
use App\Enums\HumanResources\Overtime\OvertimeApproverRoleEnum;
use App\Models\HumanResources\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\HumanResources\OvertimeRequest;

/**
 * @property int $id
 * @property int $overtime_request_id
 * @property int $approver_employee_id
 * @property OvertimeApproverRoleEnum $role
 * @property OvertimeApproverDecisionEnum $decision
 * @property string|null $decision_note
 * @property \Illuminate\Support\Carbon|null $decided_at
 */
class OvertimeRequestApprover extends Model
{
    protected $guarded = [];

    protected $casts = [
        'role'     => OvertimeApproverRoleEnum::class,
        'decision' => OvertimeApproverDecisionEnum::class,
        'decided_at' => 'datetime',
    ];

    public function overtimeRequest(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequest::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }
}
