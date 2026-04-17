<?php

namespace App\Models\HumanResources;

use App\Enums\HumanResources\Overtime\OvertimeApproverDecisionEnum;
use App\Enums\HumanResources\Overtime\OvertimeApproverRoleEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $overtime_request_id
 * @property int $approver_employee_id
 * @property OvertimeApproverRoleEnum $role
 * @property OvertimeApproverDecisionEnum $decision
 * @property string|null $decision_note
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Employee|null $approver
 * @property-read OvertimeRequest $overtimeRequest
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRequestApprover newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRequestApprover newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeRequestApprover query()
 * @mixin \Eloquent
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
