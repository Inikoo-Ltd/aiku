<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Enums\Production\Artisan\ArtisanPayTypeEnum;
use App\Actions\Production\ManufactureTaskSession\StopManufactureTaskSessionsAfterShift;
use App\Events\BroadcastManufactureFloorChanged;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionTimeEditReasonEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionUnderTargetReasonEnum;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $production_id
 * @property int|null $job_order_item_task_id
 * @property int|null $manufacture_task_id
 * @property int|null $job_order_id
 * @property int $user_id
 * @property int|null $employee_id
 * @property ManufactureTaskSessionStateEnum $state
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property numeric $quantity_made
 * @property numeric $quantity_rejected
 * @property numeric|null $task_work_cost
 * @property string|null $operative_reward_terms
 * @property string|null $operative_reward_allowance_type
 * @property numeric|null $operative_reward_amount
 * @property int $break_minutes
 * @property ManufactureTaskSessionActivityTypeEnum $activity_type
 * @property string|null $non_productive_reason
 * @property int|null $preparation_reason_id
 * @property string|null $preparation_reason_note
 * @property string|null $preparation_reason_note_en
 * @property int|null $preparation_reason_note_language_id
 * @property numeric|null $hours
 * @property numeric|null $units_per_hour
 * @property int|null $pay_band_id
 * @property string|null $band_code
 * @property numeric|null $hourly_rate
 * @property numeric|null $pay
 * @property numeric|null $bonus
 * @property numeric|null $standard_rate
 * @property bool $is_under_target
 * @property ManufactureTaskSessionUnderTargetReasonEnum|null $under_target_reason
 * @property string|null $under_target_note
 * @property int|null $under_target_reviewed_by
 * @property \Illuminate\Support\Carbon|null $under_target_reviewed_at
 * @property bool $is_combined
 * @property ArtisanPayTypeEnum $artisan_pay_type
 * @property \Illuminate\Support\Carbon|null $original_started_at
 * @property \Illuminate\Support\Carbon|null $original_ended_at
 * @property ManufactureTaskSessionTimeEditReasonEnum|null $time_edit_reason
 * @property string|null $time_edit_note
 * @property int|null $time_edited_by
 * @property \Illuminate\Support\Carbon|null $time_edited_at
 * @property \Illuminate\Support\Carbon|null $left_open_after_shift_at
 * @property string|null $shift_end_answer
 * @property int|null $moved_into_session_id
 * @property int|null $moved_seconds
 * @property \Illuminate\Support\Carbon|null $shift_end_resolved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Employee|null $employee
 * @property-read Group|null $group
 * @property-read \App\Models\Production\JobOrderItemTask|null $jobOrderItemTask
 * @property-read \App\Models\Production\JobOrder|null $jobOrder
 * @property-read \App\Models\Production\ManufactureTask|null $manufactureTask
 * @property-read ManufactureTaskSession|null $movedInto
 * @property-read ManufactureTaskSession|null $movedPreparation
 * @property-read \App\Models\Production\ManufacturePayBand|null $payBand
 * @property-read \App\Models\Production\ManufacturePreparationReason|null $preparationReason
 * @property-read Organisation $organisation
 * @property-read \App\Models\Production\Production|null $production
 * @property-read User|null $user
 * @property-read User|null $underTargetReviewer
 * @property-read User|null $timeEditor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Production\ManufactureTaskSessionShare> $shares
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManufactureTaskSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManufactureTaskSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManufactureTaskSession query()
 * @mixin \Eloquent
 */
class ManufactureTaskSession extends Model implements Auditable
{
    use HasHistory;

    public const int DEFAULT_SHORT_PREPARATION_MINUTES = 10;

    /**
     * Who changed the times, units or pay of a finished session is kept in the history; opening and closing on the floor are the session itself.
     */
    protected array $auditEvents = [
        'updated',
        'deleted',
    ];

    protected array $auditInclude = [
        'started_at',
        'ended_at',
        'quantity_made',
        'quantity_rejected',
        'state',
        'manufacture_task_id',
        'under_target_reason',
        'under_target_note',
        'time_edit_reason',
        'time_edit_note',
        'band_code',
        'pay',
    ];

    protected static function booted(): void
    {
        static::saved(fn (self $model) => BroadcastManufactureFloorChanged::dispatch($model->production_id));
    }

    protected $guarded = [];

    protected $casts = [
        'state'         => ManufactureTaskSessionStateEnum::class,
        'activity_type' => ManufactureTaskSessionActivityTypeEnum::class,
        'started_at'    => 'datetime',
        'ended_at'      => 'datetime',
        'is_under_target'          => 'boolean',
        'is_combined'              => 'boolean',
        'under_target_reason'      => ManufactureTaskSessionUnderTargetReasonEnum::class,
        'under_target_reviewed_at' => 'datetime',
        'artisan_pay_type'         => ArtisanPayTypeEnum::class,
        'original_started_at'      => 'datetime',
        'original_ended_at'        => 'datetime',
        'time_edit_reason'         => ManufactureTaskSessionTimeEditReasonEnum::class,
        'time_edited_at'           => 'datetime',
        'left_open_after_shift_at' => 'datetime',
        'shift_end_resolved_at'    => 'datetime',
        'tier_thresholds'          => 'array',
    ];

    /**
     * Preparation and cleaning: timed and paid at the base rate, with no step, quantity or target.
     */
    public function isNonProductive(): bool
    {
        return $this->job_order_item_task_id === null;
    }

    /**
     * The product made, or every product of a combined batch, e.g. "HCS-48 + SLHCS-48".
     */
    public function artefactCode(): ?string
    {
        if (!$this->is_combined) {
            return $this->jobOrderItemTask?->jobOrderItem?->artefact?->code;
        }

        $tasks = $this->shares->isNotEmpty()
            ? $this->shares->map(fn (ManufactureTaskSessionShare $share) => $share->jobOrderItemTask)
            : $this->jobOrderItemTask->combinedGroup();

        return $tasks->map(fn (JobOrderItemTask $task) => $task->jobOrderItem?->artefact?->code)->filter()->unique()->implode(' + ');
    }

    public function activityLabel(): string
    {
        return ManufactureTaskSessionActivityTypeEnum::labels()[$this->activity_type->value];
    }

    public function breakSecondsUntil(Carbon $until): int
    {
        return ManufactureBreak::where('user_id', $this->user_id)
            ->where('started_at', '<', $until)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $this->started_at))
            ->get()
            ->sum(fn (ManufactureBreak $break) => $break->secondsWithin($this->started_at, $until));
    }

    public function workedSecondsUntil(Carbon $until): int
    {
        return max(0, (int) $this->started_at->diffInSeconds($until) - $this->breakSecondsUntil($until));
    }

    /**
     * The kiosk clock-out made before the shift was flagged and still running when it was: where the work stopped for the day.
     * A break back within the grace, or a clock-out the next day, is not it.
     */
    public function shiftEndPause(): ?ManufactureBreak
    {
        if (!$this->left_open_after_shift_at) {
            return null;
        }

        $flaggedAt = $this->left_open_after_shift_at->copy()->addMinutes(StopManufactureTaskSessionsAfterShift::GRACE_MINUTES);

        return ManufactureBreak::where('user_id', $this->user_id)
            ->whereNull('planned_minutes')
            ->where('started_at', '>=', $this->started_at)
            ->where('started_at', '<=', $flaggedAt)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $flaggedAt))
            ->orderBy('started_at')
            ->first();
    }

    public function shiftEndPausedAt(): ?Carbon
    {
        return $this->shiftEndPause()?->started_at ?? $this->left_open_after_shift_at;
    }

    public function paidHours(): float
    {
        return max(0, $this->started_at->diffInSeconds($this->ended_at) / 3600 - $this->break_minutes / 60);
    }

    public function recipeStandardRate(): ?float
    {
        if (!$this->manufactureTask?->is_piece_rate) {
            return null;
        }

        if ($this->is_combined) {
            return $this->combinedStandardRate();
        }

        return $this->stepStandardRate($this->jobOrderItemTask);
    }

    /**
     * The step's target on the day the session was worked, so it is the one it started under (HELP-4461).
     * The pay band rates are the ones in force when it ended, as in CalculateManufactureTaskSessionPay.
     * Steps whose target was never changed since targets got dates read the recipe directly. Each step of the
     * recipe has its own target, also when its task is in the recipe more than once (HELP-4544).
     */
    private function stepStandardRate(?JobOrderItemTask $task): ?float
    {
        $stepRate   = null;
        $artefactId = $task?->jobOrderItem?->artefact_id;
        if ($artefactId) {
            $target   = ArtefactStepTarget::rateAt($artefactId, $this->manufacture_task_id, $this->started_at ?? now(), $task->label);
            $stepRate = $target['found']
                ? $target['standard_rate']
                : ArtefactStepTarget::withLabel(ArtefactManufactureTask::query(), $task->label)
                    ->where('artefact_id', $artefactId)
                    ->where('manufacture_task_id', $this->manufacture_task_id)
                    ->whereNotNull('standard_rate')
                    ->value('standard_rate');
        }

        $rate = $stepRate ?? $this->manufactureTask?->standard_rate;

        return $rate === null ? null : (float) $rate;
    }

    /**
     * One batch for several recipes: the rate whose hours equal the hours each line would take at
     * its own rate, so combining never changes the target compared with separate batches.
     */
    private function combinedStandardRate(): ?float
    {
        $weights = $this->shares->isNotEmpty()
            ? $this->shares->mapWithKeys(fn (ManufactureTaskSessionShare $share) => [$share->job_order_item_task_id => (float) $share->share])
            : self::combinedWeights($this->jobOrderItemTask->combinedGroup());

        $hoursPerUnit = 0.0;
        foreach (JobOrderItemTask::whereIn('id', $weights->keys())->with('jobOrderItem')->get() as $task) {
            $rate = $this->stepStandardRate($task);
            if (!$rate) {
                return null;
            }
            $hoursPerUnit += $weights[$task->id] / $rate;
        }

        return $hoursPerUnit > 0 ? 1 / $hoursPerUnit : null;
    }

    /**
     * How a combined batch divides between its lines: by what each still has to make, or by what
     * each was asked for once nothing is left on any of them.
     *
     * @param Collection<int, JobOrderItemTask> $tasks
     * @return Collection<int, float> fractions summing to 1, keyed by task id
     */
    public static function combinedWeights(Collection $tasks): Collection
    {
        $basis = $tasks->mapWithKeys(fn (JobOrderItemTask $task) => [$task->id => max(0, (float) $task->quantity_required - (float) $task->quantity_made)]);
        if ($basis->sum() <= 0) {
            $basis = $tasks->mapWithKeys(fn (JobOrderItemTask $task) => [$task->id => (float) $task->quantity_required]);
        }
        $total = $basis->sum();

        return $basis->map(fn (float $quantity) => $total > 0 ? $quantity / $total : 1 / $basis->count());
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function payBand(): BelongsTo
    {
        return $this->belongsTo(ManufacturePayBand::class, 'pay_band_id');
    }

    /**
     * Preparation shorter than this is part of the job step started straight after it, and asks for no reason.
     */
    public static function shortPreparationMinutes(Production $production): int
    {
        return (int) ($production->settings['short_preparation_minutes'] ?? self::DEFAULT_SHORT_PREPARATION_MINUTES);
    }

    public function isShortPreparation(Carbon $until): bool
    {
        return $this->activity_type == ManufactureTaskSessionActivityTypeEnum::PREPARATION
            && $this->started_at->diffInSeconds($until) < self::shortPreparationMinutes($this->production) * 60;
    }

    /**
     * The step's name as on the floor (Mixing – Red), or the task's for a session on no job step.
     */
    public function stepName(): ?string
    {
        if ($this->jobOrderItemTask?->manufactureTask) {
            return $this->jobOrderItemTask->stepName();
        }

        return $this->manufactureTask?->name;
    }

    public function movedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'moved_into_session_id');
    }

    public function movedPreparation(): HasOne
    {
        return $this->hasOne(self::class, 'moved_into_session_id');
    }

    public function preparationReason(): BelongsTo
    {
        return $this->belongsTo(ManufacturePreparationReason::class)->withTrashed();
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ManufactureTaskSessionShare::class);
    }

    public function jobOrderItemTask(): BelongsTo
    {
        return $this->belongsTo(JobOrderItemTask::class);
    }

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function manufactureTask(): BelongsTo
    {
        return $this->belongsTo(ManufactureTask::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function underTargetReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'under_target_reviewed_by');
    }

    public function timeEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'time_edited_by');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
