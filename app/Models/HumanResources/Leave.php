<?php

namespace App\Models\HumanResources;

use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Enums\HumanResources\Leave\LeaveTypeEnum;
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
 * @property LeaveTypeEnum $type
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property int $duration_days
 * @property string|null $reason
 * @property LeaveStatusEnum $status
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\HumanResources\Employee $employee
 * @property-read \App\Models\SysAdmin\User|null $approver
 */
class Leave extends Model implements HasMedia
{
    use InOrganisation;
    use SoftDeletes;
    use InteractsWithMedia;

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'approved_at'  => 'datetime',
        'type'         => LeaveTypeEnum::class,
        'status'       => LeaveStatusEnum::class,
        'data'         => 'array',
    ];

    protected $guarded = [];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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
