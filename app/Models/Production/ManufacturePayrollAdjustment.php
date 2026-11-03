<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 10:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A change to hours, quantities or pay of a pay week already locked for payroll: who made it, when, what changed and why,
 * and what it does to the artisan's pay, which is paid or deducted in the pay run of the week it was made. A change by
 * anyone but the accountant stays highlighted until the accountant has seen and approved it.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $production_id
 * @property int|null $user_id
 * @property string $model_type
 * @property int $model_id
 * @property string $change
 * @property Carbon $pay_week
 * @property array $before
 * @property array $after
 * @property string $reason
 * @property string $hours_delta
 * @property string $amount_delta
 * @property int|null $changed_by_user_id
 * @property bool $needs_approval
 * @property int|null $approved_by_user_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Production $production
 * @property-read User|null $user
 * @property-read User|null $changedBy
 * @property-read User|null $approvedBy
 * @property-read Model $model
 * @mixin \Eloquent
 */
class ManufacturePayrollAdjustment extends Model implements Auditable
{
    use HasHistory;

    protected $guarded = [];

    protected array $auditEvents = [
        'updated',
        'deleted',
    ];

    protected array $auditInclude = [
        'approved_by_user_id',
        'approved_at',
    ];

    /**
     * The record of a change to money is written once: only the accountant's approval is added later, and it is never deleted.
     */
    protected static function booted(): void
    {
        static::updating(function (self $adjustment) {
            if (array_diff(array_keys($adjustment->getDirty()), ['approved_by_user_id', 'approved_at', 'updated_at'])) {
                throw new LogicException('A payroll adjustment cannot be changed, only approved');
            }
        });
        static::deleting(fn () => throw new LogicException('A payroll adjustment cannot be deleted'));
    }

    protected function casts(): array
    {
        return [
            'pay_week'       => 'date',
            'before'         => 'array',
            'after'          => 'array',
            'needs_approval' => 'boolean',
            'approved_at'    => 'datetime',
        ];
    }

    /**
     * A pay week runs Monday to Sunday and stays open until the end of the Monday after it in the organisation's time,
     * since the accountant pays it on the Tuesday. Everything before the returned moment is locked. Payroll puts a session
     * in the week of the date it ended on as stored, so the boundary is that Monday's date at 00:00 stored time, not local
     * midnight: what payroll paid in a week is exactly what is locked with it.
     */
    public static function lockedBefore(Production $production, ?Carbon $now = null): Carbon
    {
        return Carbon::parse(($now ?? now())->copy()->setTimezone(self::timezone($production))->subDay()->startOfWeek(Carbon::MONDAY)->toDateString());
    }

    public static function payWeekOf(Production $production, Carbon $moment): Carbon
    {
        return Carbon::parse($moment->toDateString())->startOfWeek(Carbon::MONDAY);
    }

    /**
     * The pay run of a period is paid the day after it ends, so it settles the changes to earlier weeks made from the day
     * after it starts until the day after it ends: from its Tuesday to the next Monday, the same days its weeks are open.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string} settled from, settled to, earliest pay week of the period itself
     */
    public static function settledIn(Carbon $from, Carbon $to): array
    {
        return [
            $from->copy()->addDay()->startOfDay(),
            $to->copy()->addDay()->endOfDay(),
            $from->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
        ];
    }

    public static function timezone(Production $production): string
    {
        return $production->organisation->timezone?->name ?? config('app.timezone');
    }

    /**
     * The accountant and the production manager (an organisation admin): accounting edit, not just view.
     */
    public static function canChangeLockedWeek(User $user, Production $production): bool
    {
        return $user->authTo("accounting.$production->organisation_id.edit");
    }

    public static function isAccountant(User $user, Production $production): bool
    {
        return self::canChangeLockedWeek($user, $production) && !$user->authTo('org-supervisor.'.$production->organisation_id);
    }

    /**
     * Open weeks stay with the floor: supervisors and the production manager.
     */
    public static function canChangeOpenWeek(User $user, Production $production): bool
    {
        return $user->authTo([
            'org-supervisor.'.$production->organisation_id,
            "productions_operations.$production->id.orchestrate",
        ]);
    }

    public function isAwaitingApproval(): bool
    {
        return $this->needs_approval && !$this->approved_at;
    }

    public function changeLabel(): string
    {
        return match ($this->change) {
            'times'               => __('Edit times'),
            'quantity'            => __('Correct'),
            'void'                => __('Void'),
            'under_target_reason' => __('Log reason'),
            'keep_as_preparation' => __('Keep as preparation'),
            'break_end'           => __('Break end'),
            'daily_hours'         => __('Daily hours'),
            'guru'                => __('Guru'),
            'clocking'            => __('Clocking'),
            default               => $this->change,
        };
    }

    /**
     * @return array<int, array{field: string, before: string|null, after: string|null}>
     */
    public function describeChanges(bool $canSeePay = true): array
    {
        $labels   = [
            'started_at'          => __('Start'),
            'ended_at'            => __('End'),
            'hours'               => __('Hours'),
            'band_code'           => __('Band'),
            'pay'                 => __('Pay'),
            'quantity_made'       => __('Units'),
            'manufacture_task_id' => __('Step'),
            'state'               => __('State'),
            'under_target_reason' => __('Reason'),
            'under_target_note'   => __('Note'),
            'minutes'             => __('Break minutes'),
            'resolution'          => __('Answer'),
            'resolution_reason'   => __('Reason'),
            'resolution_note'     => __('Note'),
            'guru_since'          => __('Guru since'),
            'guru_until'          => __('Guru until'),
        ];
        $timezone = self::timezone($this->production);
        $format   = function ($value, string $field) use ($timezone) {
            if ($value === null || $value === '') {
                return null;
            }
            if (in_array($field, ['started_at', 'ended_at'])) {
                return Carbon::parse($value)->setTimezone($timezone)->format('D j M H:i');
            }
            if (in_array($field, ['guru_since', 'guru_until'])) {
                return Carbon::parse($value)->format('j M Y');
            }
            if ($field == 'pay') {
                return number_format((float) $value, 2);
            }
            if (is_numeric($value)) {
                return (string) (float) $value;
            }

            return is_array($value) ? json_encode($value) : (string) $value;
        };

        $fields = array_filter(array_keys($this->before + $this->after), fn (string $field) => $canSeePay || $field != 'pay');

        return array_values(array_map(fn (string $field) => [
            'field'  => $labels[$field] ?? $field,
            'before' => $format($this->before[$field] ?? null, $field),
            'after'  => $format($this->after[$field] ?? null, $field),
        ], $fields));
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayrollChange(bool $canSeePay, ?User $viewer): array
    {
        return [
            'id'                 => $this->id,
            'worker'             => $this->user?->contact_name ?: $this->user?->username,
            'pay_week'           => $this->pay_week->toDateString(),
            'change'             => $this->changeLabel(),
            'changes'            => $this->describeChanges($canSeePay),
            'reason'             => $this->reason,
            'changed_by'         => $this->changedBy?->contact_name ?: $this->changedBy?->username,
            'changed_at'         => $this->created_at,
            'amount_delta'       => $canSeePay ? (float) $this->amount_delta : null,
            'hours_delta'        => (float) $this->hours_delta,
            'awaiting_approval'  => $this->isAwaitingApproval(),
            'approved_by'        => $this->approvedBy?->contact_name ?: $this->approvedBy?->username,
            'approved_at'        => $this->approved_at,
            'approve_route'      => $this->isAwaitingApproval() && $viewer && $viewer->id != $this->changed_by_user_id && self::isAccountant($viewer, $this->production) ? [
                'name'       => 'grp.models.manufacture-payroll-adjustment.approve',
                'parameters' => ['manufacturePayrollAdjustment' => $this->id],
            ] : null,
        ];
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
