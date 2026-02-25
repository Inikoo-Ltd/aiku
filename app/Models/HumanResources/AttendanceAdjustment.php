<?php

namespace App\Models\HumanResources;

use App\Enums\HumanResources\Attendance\AttendanceAdjustmentStatusEnum;
use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $employee_id
 * @property string $employee_name
 * @property int|null $timesheet_id
 * @property \Illuminate\Support\Carbon $date
 * @property \Illuminate\Support\Carbon|null $original_start_at
 * @property \Illuminate\Support\Carbon|null $original_end_at
 * @property \Illuminate\Support\Carbon|null $requested_start_at
 * @property \Illuminate\Support\Carbon|null $requested_end_at
 * @property string $reason
 * @property AttendanceAdjustmentStatusEnum $status
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property string|null $approval_comment
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\HumanResources\Employee $employee
 * @property-read \App\Models\HumanResources\Timesheet|null $timesheet
 * @property-read \App\Models\SysAdmin\User|null $approver
 */
class AttendanceAdjustment extends Model implements HasMedia
{
    use InOrganisation;
    use SoftDeletes;
    use InteractsWithMedia;

    protected $casts = [
        'date'               => 'date',
        'original_start_at'  => 'datetime',
        'original_end_at'    => 'datetime',
        'requested_start_at' => 'datetime',
        'requested_end_at'   => 'datetime',
        'approved_at'        => 'datetime',
        'status'             => AttendanceAdjustmentStatusEnum::class,
        'data'               => 'array',
    ];

    protected $guarded = [];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png']);
    }
}
