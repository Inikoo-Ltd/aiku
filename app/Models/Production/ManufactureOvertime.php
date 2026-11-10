<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Enums\Production\ManufactureOvertime\ManufactureOvertimeCompensationEnum;
use App\Enums\Production\ManufactureOvertime\ManufactureOvertimeDecisionEnum;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A manager's decision on an artisan's overtime for a day, taken ahead with the planned times or after the day: approved,
 * paid or as time off in lieu (TOIL), or not authorised with a reason. Weekend overtime approved as paid is paid at 1.5 x
 * band 0; TOIL hours are not paid but owed to the artisan as time off.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $production_id
 * @property int $employee_id
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon $date
 * @property \Illuminate\Support\Carbon|null $planned_start
 * @property \Illuminate\Support\Carbon|null $planned_end
 * @property ManufactureOvertimeDecisionEnum $decision
 * @property ManufactureOvertimeCompensationEnum|null $compensation
 * @property string|null $toil_ratio
 * @property string|null $reason
 * @property int|null $decided_clocked_minutes
 * @property int $expected_minutes the shift less its unpaid break when decided
 * @property int|null $approved_minutes
 * @property int|null $decided_by_user_id
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Production $production
 * @property-read Employee $employee
 * @property-read User|null $user
 * @property-read User|null $decidedBy
 * @mixin \Eloquent
 */
class ManufactureOvertime extends Model implements Auditable
{
    use HasHistory;

    public const float WEEKEND_MULTIPLIER = 1.5;

    public const float DEFAULT_TOIL_RATIO = 1.0;

    protected $guarded = [];

    protected array $auditInclude = [
        'planned_start',
        'planned_end',
        'decision',
        'compensation',
        'toil_ratio',
        'reason',
        'approved_minutes',
        'decided_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'date'          => 'date',
            'planned_start' => 'datetime',
            'planned_end'   => 'datetime',
            'decision'      => ManufactureOvertimeDecisionEnum::class,
            'compensation'  => ManufactureOvertimeCompensationEnum::class,
            'decided_at'    => 'datetime',
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

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isPlanned(): bool
    {
        return $this->planned_start !== null && $this->planned_end !== null;
    }

    public function isApproved(): bool
    {
        return $this->decision == ManufactureOvertimeDecisionEnum::APPROVED;
    }

    public function isToil(): bool
    {
        return $this->isApproved() && $this->compensation == ManufactureOvertimeCompensationEnum::TOIL;
    }

    public function isPaidAtWeekendRate(): bool
    {
        return $this->isApproved() && $this->compensation == ManufactureOvertimeCompensationEnum::PAID && $this->date->isWeekend();
    }

    /**
     * The overtime of a day this decision covers: what was clocked beyond the shift as it was when decided, up to the
     * minutes approved. Time clocked beyond that is paid as normal hours and the day is flagged.
     */
    public function coveredMinutes(int $clockedMinutes): int
    {
        return min(max(0, $clockedMinutes - $this->expected_minutes), (int) $this->approved_minutes);
    }

    public static function toilRatio(Production $production): float
    {
        return (float) ($production->settings['toil_ratio'] ?? self::DEFAULT_TOIL_RATIO);
    }

    /**
     * TOIL owed to each artisan up to a day: the overtime covered on days approved as TOIL, times the ratio set when it was
     * approved, less the TOIL taken. Days are read as last worked out on Daily hours or Payroll, and where a paid time rule limits the
     * day to its shift the overtime is the time outside the shift the approval covers, as payroll has it.
     *
     * @param  array<int, int>|null  $employeeIds
     * @return array<int, int> minutes keyed by employee id
     */
    public static function toilBalances(Production $production, string $upTo, ?array $employeeIds = null): array
    {
        $earned = DB::table('manufacture_overtimes')
            ->join('manufacture_artisan_days', 'manufacture_artisan_days.manufacture_overtime_id', '=', 'manufacture_overtimes.id')
            ->where('manufacture_overtimes.production_id', $production->id)
            ->where('manufacture_overtimes.decision', ManufactureOvertimeDecisionEnum::APPROVED->value)
            ->where('manufacture_overtimes.compensation', ManufactureOvertimeCompensationEnum::TOIL->value)
            ->where('manufacture_overtimes.date', '<=', $upTo)
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('manufacture_overtimes.employee_id', $employeeIds))
            ->groupBy('manufacture_overtimes.employee_id')
            ->selectRaw('manufacture_overtimes.employee_id, sum(coalesce(round(manufacture_artisan_days.paid_overtime_seconds / 60.0), least(greatest(0, coalesce(manufacture_artisan_days.clocked_minutes, 0) - manufacture_overtimes.expected_minutes), coalesce(manufacture_overtimes.approved_minutes, 0))) * manufacture_overtimes.toil_ratio) as minutes')
            ->pluck('minutes', 'employee_id');

        $taken = ManufactureToilTaken::where('production_id', $production->id)
            ->where('date', '<=', $upTo)
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds))
            ->groupBy('employee_id')
            ->selectRaw('employee_id, sum(minutes) as minutes')
            ->pluck('minutes', 'employee_id');

        $balances = [];
        foreach ($earned->keys()->merge($taken->keys())->unique() as $employeeId) {
            $balances[(int) $employeeId] = (int) round((float) ($earned[$employeeId] ?? 0)) - (int) ($taken[$employeeId] ?? 0);
        }

        return $balances;
    }
}
