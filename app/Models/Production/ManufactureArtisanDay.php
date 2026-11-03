<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Oct 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Enums\Production\ManufactureArtisanDay\ManufactureArtisanDayResolutionEnum;
use App\Enums\Production\ManufactureArtisanDay\ManufactureArtisanDayShortReasonEnum;
use App\Enums\Production\ManufactureArtisanDay\ManufactureArtisanDayStatusEnum;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * One artisan's day: the time clocked (clocked breaks left out) against the shift in their profile, the time recorded on
 * the floor tablet beside it, and how a supervisor dealt with a day over or under it. The figures are worked out again
 * from the timesheet and the sessions each time the days are looked at; the decision stays, and only counts while the
 * clocked time is unchanged.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $production_id
 * @property int $employee_id
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon $date
 * @property \Illuminate\Support\Carbon|null $shift_start
 * @property \Illuminate\Support\Carbon|null $shift_end
 * @property int|null $expected_minutes
 * @property int $recorded_minutes tablet time
 * @property int|null $clocked_minutes
 * @property int $tablet_over_clocked_minutes
 * @property int $open_clockings
 * @property int $tolerance_minutes
 * @property int $unchecked_running_jobs
 * @property ManufactureArtisanDayStatusEnum $status
 * @property bool $is_warning_open
 * @property ManufactureArtisanDayResolutionEnum|null $resolution
 * @property ManufactureArtisanDayShortReasonEnum|null $resolution_reason
 * @property string|null $resolution_note
 * @property int|null $resolved_minutes
 * @property int|null $resolved_by_user_id
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Production $production
 * @property-read Employee $employee
 * @property-read User|null $user
 * @property-read User|null $resolvedBy
 * @mixin \Eloquent
 */
class ManufactureArtisanDay extends Model implements Auditable
{
    use HasHistory;

    protected $guarded = [];

    /**
     * Only the answers people give are history; the figures are worked out again whenever the days are looked at.
     */
    protected array $auditEvents = [
        'updated',
        'deleted',
    ];

    protected array $auditInclude = [
        'resolution',
        'resolution_reason',
        'resolution_note',
        'resolved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'date'              => 'date',
            'shift_start'       => 'datetime',
            'shift_end'         => 'datetime',
            'status'            => ManufactureArtisanDayStatusEnum::class,
            'is_warning_open'   => 'boolean',
            'resolution'        => ManufactureArtisanDayResolutionEnum::class,
            'resolution_reason' => ManufactureArtisanDayShortReasonEnum::class,
            'resolved_at'       => 'datetime',
        ];
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function isResolved(): bool
    {
        return $this->resolution
            && $this->resolution->answers($this->status)
            && $this->resolved_minutes === ($this->clocked_minutes ?? 0);
    }

    public function needsAction(): bool
    {
        return $this->status == ManufactureArtisanDayStatusEnum::RUNNING_JOB
            || $this->tablet_over_clocked_minutes > 0
            || (in_array($this->status, [ManufactureArtisanDayStatusEnum::OVER, ManufactureArtisanDayStatusEnum::UNDER, ManufactureArtisanDayStatusEnum::NO_BREAK]) && !$this->isResolved());
    }
}
