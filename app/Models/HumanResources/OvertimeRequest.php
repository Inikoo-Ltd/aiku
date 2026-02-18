<?php

namespace App\Models\HumanResources;

use App\Enums\HumanResources\Overtime\OvertimeRequestSourceEnum;
use App\Enums\HumanResources\Overtime\OvertimeRequestStatusEnum;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $employee_id
 * @property int $overtime_type_id
 * @property OvertimeRequestSourceEnum $source
 * @property \Illuminate\Support\Carbon $requested_date
 * @property \Illuminate\Support\Carbon|null $requested_start_at
 * @property \Illuminate\Support\Carbon|null $requested_end_at
 * @property int $requested_duration_minutes
 * @property string|null $reason
 * @property OvertimeRequestStatusEnum $status
 * @property string|null $decision_note
 * @property int|null $approved_by_employee_id
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $recorded_start_at
 * @property \Illuminate\Support\Carbon|null $recorded_end_at
 * @property int|null $recorded_duration_minutes
 * @property int|null $recorded_by_employee_id
 * @property int $lieu_requested_minutes
 * @property int|null $requested_by_employee_id
 */
class OvertimeRequest extends Model
{
    use InOrganisation;

    protected $guarded = [];

    protected $casts = [
        'requested_date'            => 'date',
        'requested_start_at'        => 'datetime',
        'requested_end_at'          => 'datetime',
        'approved_at'               => 'datetime',
        'rejected_at'               => 'datetime',
        'cancelled_at'              => 'datetime',
        'recorded_start_at'         => 'datetime',
        'recorded_end_at'           => 'datetime',
        'requested_duration_minutes' => 'integer',
        'recorded_duration_minutes' => 'integer',
        'lieu_requested_minutes'    => 'integer',
        'status'                    => OvertimeRequestStatusEnum::class,
        'source'                    => OvertimeRequestSourceEnum::class,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function overtimeType(): BelongsTo
    {
        return $this->belongsTo(OvertimeType::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by_employee_id');
    }

    public function approver(): BelongsTo
    {
        return $this->approvedBy();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'recorded_by_employee_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by_employee_id');
    }

    public function approvers(): HasMany
    {
        return $this->hasMany(OvertimeRequestApprover::class);
    }
}
