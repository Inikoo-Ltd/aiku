<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Events\BroadcastManufactureFloorChanged;
use App\Events\BroadcastPartnerProductionChanged;
use App\Actions\Production\JobOrderItemTask\CalculateJobOrderItemTaskQuantities;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $production_id
 * @property int $job_order_id
 * @property int $job_order_item_id
 * @property int $manufacture_task_id
 * @property int $position
 * @property JobOrderItemTaskStateEnum $state
 * @property numeric $quantity_required
 * @property numeric $quantity_made
 * @property numeric $quantity_rejected
 * @property int|null $combined_task_id
 * @property int|null $employee_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group|null $group
 * @property-read \App\Models\Production\JobOrder|null $jobOrder
 * @property-read \App\Models\Production\JobOrderItem|null $jobOrderItem
 * @property-read \App\Models\Production\ManufactureTask|null $manufactureTask
 * @property-read Organisation $organisation
 * @property-read \App\Models\Production\Production|null $production
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Production\ManufactureTaskSession> $sessions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Production\ManufactureTaskSessionShare> $sessionShares
 * @property-read \Illuminate\Database\Eloquent\Collection<int, JobOrderItemTask> $combinedMembers
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItemTask newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItemTask newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItemTask query()
 * @mixin \Eloquent
 */
class JobOrderItemTask extends Model
{
    /**
     * Who a step is for: its own operator, else the sub-job's, else the job order's.
     * Needs job_order_items and job_orders joined.
     */
    public const string ASSIGNEE_SQL = 'coalesce(job_order_item_tasks.employee_id, job_order_items.employee_id, job_orders.employee_id)';

    protected static function booted(): void
    {
        static::saved(function (self $model) {
            BroadcastManufactureFloorChanged::dispatch($model->production_id);
            if ($model->wasChanged('state')) {
                BroadcastPartnerProductionChanged::dispatchForJobOrder($model->job_order_id);
            }
        });
    }

    protected $guarded = [];

    protected $casts = [
        'state' => JobOrderItemTaskStateEnum::class,
    ];

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

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function jobOrderItem(): BelongsTo
    {
        return $this->belongsTo(JobOrderItem::class);
    }

    public function manufactureTask(): BelongsTo
    {
        return $this->belongsTo(ManufactureTask::class)->withTrashed();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function unitsPerArtefact(): float
    {
        $units = (float) ($this->recipeStep()?->units_per_artefact ?: 1);

        return $this->isRecordedInKg() ? $units : ArtefactManufactureTask::exactUnits($units);
    }

    public function requiredFor(float|int|string $artefacts): float
    {
        return round((float) $artefacts * $this->unitsPerArtefact(), 3);
    }

    public function artefactsIn(float|int|string|null $stepQuantity = null): float
    {
        return floor(round((float) ($stepQuantity ?? $this->quantity_made) / $this->unitsPerArtefact(), 3));
    }

    public function isInRecipe(): bool
    {
        return $this->recipeStep() !== null;
    }

    public function setRequiredQuantity(float $quantity): self
    {
        $this->update(['quantity_required' => $quantity]);

        return CalculateJobOrderItemTaskQuantities::run($this);
    }

    /**
     * A step left with nothing recorded and asking for more than the recipe says today comes down to
     * the recipe, so a count it kept through a recipe change because of an entry that was later moved
     * away or voided does not stay behind. A count lowered on purpose, like work credited to a carried
     * job, is never raised back.
     */
    public function followRecipeWhenNothingRecorded(): self
    {
        if ((float) $this->quantity_made > 0 || !$this->isInRecipe()) {
            return $this;
        }

        $recipeQuantity = $this->requiredFor($this->jobOrderItem->quantity);
        if ((float) $this->quantity_required <= $recipeQuantity) {
            return $this;
        }

        return $this->setRequiredQuantity($recipeQuantity);
    }

    public function mixBatchKg(): ?float
    {
        return $this->isRecordedInKg() ? ($this->recipeStep()?->mix_batch_kg ?: null) : null;
    }

    public static function isWholeMixBatches(float $quantity, float $mixBatchKg): bool
    {
        $batches = $quantity / $mixBatchKg;

        return abs($batches - round($batches)) < 1e-6;
    }

    public static function wholeMixBatchesMessage(float $mixBatchKg): string
    {
        return __('This mix is made in whole batches of :kg kg, enter :kg, :double, :triple…', ['kg' => $mixBatchKg, 'double' => round(2 * $mixBatchKg, 3), 'triple' => round(3 * $mixBatchKg, 3)]);
    }

    /**
     * A combined mix is made in batches only when every line shares the same batch size.
     *
     * @param Collection<int, JobOrderItemTask> $members
     */
    public static function sharedMixBatchKg(Collection $members): ?float
    {
        $sizes = $members->map(fn (JobOrderItemTask $member) => $member->mixBatchKg())->unique();

        return $sizes->count() == 1 ? $sizes->first() : null;
    }

    private ?ArtefactManufactureTask $recipeStep = null;

    public function refresh()
    {
        $this->recipeStep = null;

        return parent::refresh();
    }

    private function recipeStep(): ?ArtefactManufactureTask
    {
        return $this->recipeStep ??= ArtefactManufactureTask::where('artefact_id', $this->jobOrderItem->artefact_id)
            ->where('manufacture_task_id', $this->manufacture_task_id)
            ->first(['units_per_artefact', 'mix_batch_kg']);
    }

    public function isRecordedInKg(): bool
    {
        return (bool) $this->manufactureTask?->is_recorded_in_kg;
    }

    public function unit(): ?string
    {
        return $this->isRecordedInKg() ? 'kg' : null;
    }

    public function packSize(): ?int
    {
        return $this->isRecordedInKg() ? null : ArtefactManufactureTask::packSize($this->unitsPerArtefact());
    }

    /**
     * A step limited to named artisans is open to all of them until it is given to one: the
     * operator of the job or the sub-job does not inherit it.
     */
    public function isRestricted(): bool
    {
        return (bool) $this->manufactureTask?->artisans->isNotEmpty();
    }

    public function isAllowedFor(?Employee $employee): bool
    {
        return !$this->isRestricted() || ($employee && $this->manufactureTask->artisans->contains('id', $employee->id));
    }

    /**
     * Addressed to this employee: named on the step or inherited from the job, or one of the
     * allowed artisans of a restricted step nobody has been given yet.
     */
    public function isFor(?Employee $employee): bool
    {
        if (!$employee) {
            return false;
        }

        return $this->assigneeId() == $employee->id
            || ($this->employee_id === null && $this->isRestricted() && $this->isAllowedFor($employee));
    }

    public function assigneeId(): ?int
    {
        return $this->employee_id ?? ($this->isRestricted() ? null : $this->jobOrderItem->artisanId());
    }

    public function assignee(): ?Employee
    {
        if ($this->employee_id) {
            return $this->employee;
        }

        return $this->isRestricted() ? null : $this->jobOrderItem->artisan();
    }

    public function scopeUnworked(Builder $query): Builder
    {
        return $query->where('state', JobOrderItemTaskStateEnum::TODO)
            ->whereDoesntHave('sessions')
            ->whereDoesntHave('sessionShares');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereHas('jobOrder')->whereHas('jobOrderItem');
    }

    /**
     * @param Collection<int, JobOrderItemTask> $siblings tasks of the same job order item, including this one
     */
    public function blockingStep(Collection $siblings): ?JobOrderItemTask
    {
        if ($this->state != JobOrderItemTaskStateEnum::TODO) {
            return null;
        }

        $blockingTaskIds = $this->jobOrderItem->artefact->manufactureTasks
            ->filter(fn (ManufactureTask $manufactureTask) => $manufactureTask->pivot->blocks_later_steps ?? true)
            ->pluck('id');

        $firstUnfinished = $siblings
            ->filter(fn (JobOrderItemTask $sibling) => $sibling->id == $this->id || $blockingTaskIds->contains($sibling->manufacture_task_id))
            ->sortBy([['position', 'asc'], ['id', 'asc']])
            ->first(fn (JobOrderItemTask $sibling) => $sibling->state != JobOrderItemTaskStateEnum::DONE
                && ($sibling->id == $this->id || !$sibling->isRecordedInKg() || (float) $sibling->quantity_made <= 0));

        return $firstUnfinished && $firstUnfinished->id != $this->id ? $firstUnfinished : null;
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ManufactureTaskSession::class);
    }

    public function sessionShares(): HasMany
    {
        return $this->hasMany(ManufactureTaskSessionShare::class);
    }

    public function combinedMembers(): HasMany
    {
        return $this->hasMany(JobOrderItemTask::class, 'combined_task_id');
    }

    /**
     * The steps worked as one batch with this one, lead first, or just this step when it is not combined.
     *
     * @return Collection<int, JobOrderItemTask>
     */
    public function combinedGroup(): Collection
    {
        if (!$this->combined_task_id) {
            return collect([$this]);
        }

        $members = JobOrderItemTask::where('combined_task_id', $this->combined_task_id)
            ->live()
            ->orderByRaw('id = ? desc', [$this->combined_task_id])
            ->orderBy('id')
            ->get();

        return $members->count() > 1 ? $members->values() : collect([$this]);
    }

    /**
     * Who worked this step and for how long: its own closed sessions, plus its share of the
     * combined sessions it was made in. Needs sessions.user and sessionShares.session.user loaded.
     *
     * @return Collection<int, array{user: \App\Models\SysAdmin\User, hours: float, pay: float|null}>
     */
    public function closedWork(): Collection
    {
        $own = $this->sessions
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->where('is_combined', false)
            ->map(fn (ManufactureTaskSession $session) => [
                'user'  => $session->user,
                'hours' => $session->paidHours(),
                'pay'   => $session->pay === null ? null : (float) $session->pay,
            ]);

        $shared = $this->sessionShares
            ->filter(fn (ManufactureTaskSessionShare $share) => $share->session->state == ManufactureTaskSessionStateEnum::CLOSED)
            ->map(fn (ManufactureTaskSessionShare $share) => [
                'user'  => $share->session->user,
                'hours' => $share->session->paidHours() * (float) $share->share,
                'pay'   => $share->session->pay === null ? null : round((float) $share->session->pay * (float) $share->share, 2),
            ]);

        return $own->concat($shared)->values();
    }
}
