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
 * @property \Illuminate\Support\Carbon|null $clocked_in_at first clock in of the day
 * @property \Illuminate\Support\Carbon|null $clocked_out_at last clock out of the day
 * @property int|null $manufacture_overtime_id
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
 * @property-read ManufactureOvertime|null $overtime
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
            'clocked_in_at'     => 'datetime',
            'clocked_out_at'    => 'datetime',
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

    public function overtime(): BelongsTo
    {
        return $this->belongsTo(ManufactureOvertime::class, 'manufacture_overtime_id');
    }

    public function isResolved(): bool
    {
        return $this->resolution
            && $this->resolution->answers($this->status)
            && $this->resolved_minutes === ($this->clocked_minutes ?? 0);
    }

    /**
     * Clocked in before or out after the planned overtime and the shift, by more than the tolerance.
     */
    public function isOutsidePlanned(): bool
    {
        $overtime = $this->overtime;
        if (!$overtime?->isPlanned() || !$this->clocked_in_at || !$this->clocked_out_at) {
            return false;
        }

        $windowStart = $this->shift_start ? $overtime->planned_start->min($this->shift_start) : $overtime->planned_start;
        $windowEnd   = $this->shift_end ? $overtime->planned_end->max($this->shift_end) : $overtime->planned_end;

        return $this->clocked_in_at->lessThan($windowStart->copy()->subMinutes($this->tolerance_minutes))
            || $this->clocked_out_at->greaterThan($windowEnd->copy()->addMinutes($this->tolerance_minutes));
    }

    /**
     * An approval holds while the minutes it approved cover the time clocked beyond the shift (within the tolerance) and,
     * when planned, the artisan clocked within the planned times or it was saved again since. A refusal holds while the
     * clocked time is what it was when refused, or within the planned times.
     */
    public function isOvertimeDecided(): bool
    {
        $overtime = $this->overtime;
        if (!$overtime) {
            return false;
        }

        $clocked            = $this->clocked_minutes ?? 0;
        $savedSinceClocking = $overtime->decided_clocked_minutes === $clocked;
        if ($overtime->isApproved()) {
            $uncovered = max(0, $clocked - $overtime->expected_minutes) - $overtime->coveredMinutes($clocked);

            return $uncovered <= $this->tolerance_minutes && ($savedSinceClocking || !$this->isOutsidePlanned());
        }

        return $savedSinceClocking || ($overtime->isPlanned() && !$this->isOutsidePlanned());
    }

    public function isOvertimeAwaitingDecision(): bool
    {
        if ($this->overtime?->isPlanned() && !$this->isOvertimeDecided()) {
            return true;
        }

        return $this->status == ManufactureArtisanDayStatusEnum::OVER && !$this->isResolved() && !$this->isOvertimeDecided();
    }

    public function needsAction(): bool
    {
        return $this->status == ManufactureArtisanDayStatusEnum::RUNNING_JOB
            || $this->tablet_over_clocked_minutes > 0
            || $this->isOvertimeAwaitingDecision()
            || (in_array($this->status, [ManufactureArtisanDayStatusEnum::UNDER, ManufactureArtisanDayStatusEnum::NO_BREAK]) && !$this->isResolved());
    }
}
