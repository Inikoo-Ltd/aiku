<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Events\BroadcastManufactureFloorChanged;
use App\Events\BroadcastPartnerProductionChanged;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
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

        $recipeTaskIds = $this->jobOrderItem->artefact->manufactureTasks->pluck('id');

        $firstUnfinished = $siblings
            ->filter(fn (JobOrderItemTask $sibling) => $sibling->id == $this->id || $recipeTaskIds->contains($sibling->manufacture_task_id))
            ->sortBy([['position', 'asc'], ['id', 'asc']])
            ->first(fn (JobOrderItemTask $sibling) => $sibling->state != JobOrderItemTaskStateEnum::DONE);

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
