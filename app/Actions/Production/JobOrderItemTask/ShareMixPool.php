<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItemTask;

use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\ManufactureTaskSessionShare;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * A mix is shared by every open line that uses it, whatever the product or job order (HELP-4562), and so
 * is the Making of a labelled strand (Making – Light Blue) that turns it into pieces. What is recorded on
 * one line goes to the lines that need it, that line first and then in plan order. Mix is made fresh and
 * all of it is made into pieces, so mix no line needs is not kept for later lines; the extra pieces are:
 * they stay on the Making session as the strand's balance, and the next lines that need the strand take
 * them first, with their Making and their Mixing done. A balance is never stored: it is the session's
 * quantity less its shares.
 */
class ShareMixPool
{
    use AsObject;

    private const float EPSILON = 0.0005;

    private const string MAKING = 'make:';

    private const string OF_MIX = "\u{241E}";

    public static function poolKey(JobOrderItemTask $task): ?string
    {
        if (!$task->recipeStep) {
            return null;
        }
        if ($task->isRecordedInKg()) {
            return $task->recipeStep->loadMissing('rawMaterials')->mixPoolKey();
        }

        $label = mb_strtolower(trim((string) $task->label));
        if ($label === '') {
            return null;
        }

        $strand = $task->recipeStep->loadMissing('artefact.recipeSteps.manufactureTask', 'artefact.recipeSteps.rawMaterials')->artefact->recipeSteps
            ->filter(fn ($step) => mb_strtolower(trim((string) $step->label)) === $label)
            ->sortBy([['position', 'asc'], ['id', 'asc']])
            ->values();
        $mixAt = $strand->search(fn ($step) => $step->manufactureTask?->is_recorded_in_kg && $step->position <= $task->recipeStep->position);
        if ($mixAt === false || $strand->get($mixAt + 1)?->id != $task->recipeStep->id) {
            return null;
        }

        $mixStep      = $strand->get($mixAt);
        $kgPerPiece   = round((float) $mixStep->units_per_artefact / ((float) $task->recipeStep->units_per_artefact ?: 1), 6);

        return self::MAKING.$task->manufacture_task_id.'|'.$label.'|'.$kgPerPiece.self::OF_MIX.$mixStep->mixPoolKey();
    }

    public static function isMakingKey(string $poolKey): bool
    {
        return str_starts_with($poolKey, self::MAKING);
    }

    private static function mixKeyOf(string $makingKey): string
    {
        return explode(self::OF_MIX, $makingKey, 2)[1];
    }

    /**
     * What a line's later steps already used of a shared step: never given back. Later steps are those of its
     * strand and those every strand goes into (Packing).
     */
    public static function usedByLaterSteps(JobOrderItemTask $task): float
    {
        $strand    = mb_strtolower(trim((string) $task->label));
        $artefacts = JobOrderItemTask::where('job_order_item_id', $task->job_order_item_id)
            ->where('position', '>', $task->position)
            ->with('recipeStep', 'manufactureTask')
            ->get()
            ->filter(fn (JobOrderItemTask $later) => $strand === '' || in_array(mb_strtolower(trim((string) $later->label)), ['', $strand], true))
            ->map(fn (JobOrderItemTask $later) => $later->artefactsIn())
            ->max() ?? 0;

        return min((float) $task->quantity_made, $task->requiredFor($artefacts));
    }

    /**
     * @param  Collection<int, JobOrderItemTask>|null  $firstTo  lines served before the plan order, as a corrected mix goes back to the lines it went to
     * @return Collection<int, ManufactureTaskSessionShare> keyed by task id
     */
    public function shareOut(ManufactureTaskSession $session, JobOrderItemTask $lead, string $poolKey, ?Collection $firstTo = null): Collection
    {
        $this->lock($poolKey);

        $made   = (float) $session->quantity_made;
        $left   = $made;
        $shares = collect();
        $session->shares()->delete();

        $tasks = $this->tasksNeeding($session->production_id, $poolKey);
        if ($firstTo) {
            $tasks = $tasks->sortBy(fn (JobOrderItemTask $task) => $firstTo->contains('id', $task->id) ? 0 : 1)->values();
        }
        if ($this->stillNeeds($lead) > self::EPSILON) {
            $tasks = $tasks->reject(fn (JobOrderItemTask $task) => $task->id == $lead->id)->prepend($lead);
        }

        foreach ($tasks as $task) {
            if ($left <= self::EPSILON) {
                break;
            }
            $given = round(min($this->stillNeeds($task), $left), 3);
            $left  = round($left - $given, 3);

            $shares[$task->id] = $session->shares()->create([
                'job_order_item_task_id' => $task->id,
                'share'                  => $made > 0 ? round($given / $made, 6) : 0,
                'quantity_made'          => $given,
                'quantity_rejected'      => 0,
            ]);
            if (self::isMakingKey($poolKey)) {
                $this->mixFor($task, $given, self::mixKeyOf($poolKey));
            }
        }
        $session->unsetRelation('shares');

        return $shares;
    }

    /**
     * Gives the extra pieces of a strand to the open lines still waiting for it, oldest first, with the
     * mix they were made from. Mix itself is never kept for later lines.
     *
     * @param  array<int, int>  $exceptTaskIds  lines leaving their job, never given pieces
     */
    public function fill(int $productionId, string $poolKey, array $exceptTaskIds = []): void
    {
        if (!self::isMakingKey($poolKey)) {
            return;
        }
        $this->lock($poolKey);

        $balances = $this->leftovers($productionId, $poolKey);
        if ($balances->isEmpty()) {
            return;
        }

        foreach ($this->tasksNeeding($productionId, $poolKey)->whereNotIn('id', $exceptTaskIds) as $task) {
            $given = $this->giveFrom($balances, $task, $this->stillNeeds($task));
            if ($given > self::EPSILON) {
                CalculateJobOrderItemTaskQuantities::run($task);
                $this->mixFor($task, $given, self::mixKeyOf($poolKey));
            }
            $balances = $balances->filter(fn (ManufactureTaskSession $balance) => $balance->leftover_kg > self::EPSILON)->values();
            if ($balances->isEmpty()) {
                return;
            }
        }
    }

    /**
     * Pieces given to a line were made from a mix already recorded, with its raw materials: the line's mix
     * step is marked done for them from that mix, past what the mix has left if need be.
     */
    private function mixFor(JobOrderItemTask $making, float $pieces, string $mixKey): void
    {
        $mix = JobOrderItemTask::where('job_order_item_id', $making->job_order_item_id)
            ->whereNotNull('artefact_manufacture_task_id')
            ->with(['recipeStep.rawMaterials', 'manufactureTask'])
            ->get()
            ->first(fn (JobOrderItemTask $step) => self::poolKey($step) === $mixKey);
        if (!$mix) {
            return;
        }

        $kg = min($this->stillNeeds($mix), round($pieces / $making->unitsPerArtefact() * $mix->unitsPerArtefact(), 3));
        if ($kg <= self::EPSILON) {
            return;
        }

        $this->lock($mixKey);
        $left = $kg - $this->giveFrom($this->leftovers($making->production_id, $mixKey), $mix, $kg);
        if ($left > self::EPSILON && ($lastMix = $this->closedSessions($making->production_id, $mixKey)->last())) {
            $this->giveFrom(collect([tap($lastMix, fn ($session) => $session->leftover_kg = $left)]), $mix, $left);
        }
        CalculateJobOrderItemTaskQuantities::run($mix);
    }

    /**
     * @param  Collection<int, ManufactureTaskSession>  $sources  with leftover_kg, taken from in order
     */
    private function giveFrom(Collection $sources, JobOrderItemTask $task, float $need): float
    {
        $given = 0.0;
        foreach ($sources as $source) {
            if ($need - $given <= self::EPSILON) {
                break;
            }
            $part = round(min($need - $given, $source->leftover_kg), 3);
            if ($part <= self::EPSILON) {
                continue;
            }
            $share = ManufactureTaskSessionShare::firstOrNew(['manufacture_task_session_id' => $source->id, 'job_order_item_task_id' => $task->id], ['share' => 0, 'quantity_rejected' => 0, 'quantity_made' => 0]);
            $share->quantity_made = round((float) $share->quantity_made + $part, 3);
            $share->share         = (float) $source->quantity_made > 0 ? round((float) $share->quantity_made / (float) $source->quantity_made, 6) : 0;
            $share->save();

            $source->leftover_kg = round($source->leftover_kg - $part, 3);
            $given               = round($given + $part, 3);
        }

        return $given;
    }

    /**
     * A line asking for less than it was given hands the difference back, newest session first: extra pieces
     * go to the next line that needs the strand, mix stays unused. A line carried to another job keeps it:
     * its order credits it to the carried part (HELP-4509).
     */
    public function settle(JobOrderItemTask $task): void
    {
        $poolKey = self::poolKey($task);
        if (!$poolKey) {
            return;
        }

        DB::transaction(function () use ($task, $poolKey) {
            $this->lock($poolKey);
            $excess = round((float) $task->quantity_made - max((float) $task->quantity_required, self::usedByLaterSteps($task)), 3);
            if ($excess > self::EPSILON && !Arr::has($task->jobOrderItem->data ?? [], 'carried_to')) {
                $this->giveBack($task, $excess);
            }
            $this->fill($task->production_id, $poolKey);
        });
    }

    /**
     * A line leaving its job hands back what its later steps have not used: pieces first, then the mix they
     * no longer need, and only then are the extra pieces given to the lines waiting for them.
     *
     * @param  iterable<JobOrderItemTask>  $tasks
     */
    public function giveBackAll(iterable $tasks): void
    {
        $tasks      = collect($tasks)->sortByDesc('position')->values();
        $leavingIds = $tasks->pluck('id')->all();
        $poolKeys   = $tasks->mapWithKeys(fn (JobOrderItemTask $task) => [$task->id => self::poolKey($task)])->filter();

        DB::transaction(function () use ($tasks, $leavingIds, $poolKeys) {
            $poolKeys->unique()->sort()->each(fn (string $poolKey) => $this->lock($poolKey));
            foreach ($tasks as $task) {
                if (!isset($poolKeys[$task->id])) {
                    continue;
                }
                $task->refresh();
                $back = round((float) $task->quantity_made - self::usedByLaterSteps($task), 3);
                if ($back > self::EPSILON) {
                    $this->giveBack($task, $back);
                }
            }
            foreach ($poolKeys->unique() as $poolKey) {
                $this->fill($tasks->first()->production_id, $poolKey, $leavingIds);
            }
        });
    }

    private function giveBack(JobOrderItemTask $task, float $kg): void
    {
        $shares = ManufactureTaskSessionShare::where('job_order_item_task_id', $task->id)
            ->whereHas('session', fn ($query) => $query->whereNotNull('mix_pool_key')->where('state', ManufactureTaskSessionStateEnum::CLOSED))
            ->with('session')
            ->orderByDesc('manufacture_task_session_id')
            ->lockForUpdate()
            ->get();

        foreach ($shares as $share) {
            if ($kg <= self::EPSILON) {
                break;
            }
            $back = min($kg, (float) $share->quantity_made);
            $kg  -= $back;
            $left = round((float) $share->quantity_made - $back, 3);
            if ($left <= self::EPSILON) {
                $share->delete();
            } else {
                $share->update(['quantity_made' => $left, 'share' => round($left / (float) $share->session->quantity_made, 6)]);
            }
        }

        CalculateJobOrderItemTaskQuantities::run($task);
    }

    /**
     * A shared mix whose kg went into a line already received, or a job no longer open, is settled: its raw
     * materials and those goods are in stock, so it can no longer be voided or corrected.
     */
    public static function settledLineOf(ManufactureTaskSession $session): ?string
    {
        $share = $session->shares()
            ->with(['jobOrderItemTask.jobOrderItem', 'jobOrderItemTask.jobOrder'])
            ->get()
            ->first(fn (ManufactureTaskSessionShare $share) => (float) $share->jobOrderItemTask->jobOrderItem->quantity_received > 0
                || !in_array($share->jobOrderItemTask->jobOrder->state, JobOrderStateEnum::open()));

        return $share?->jobOrderItemTask->jobOrder->reference;
    }

    /**
     * What each session has not given to a line yet: kg of mix made into extra pieces, or the extra pieces.
     *
     * @return Collection<int, ManufactureTaskSession> with leftover_kg, oldest first
     */
    public function leftovers(int $productionId, string $poolKey): Collection
    {
        return $this->closedSessions($productionId, $poolKey)
            ->filter(fn (ManufactureTaskSession $session) => $session->leftover_kg > self::EPSILON)
            ->values();
    }

    /**
     * @return Collection<int, ManufactureTaskSession> with leftover_kg, oldest first
     */
    private function closedSessions(int $productionId, string $poolKey): Collection
    {
        return ManufactureTaskSession::where('production_id', $productionId)
            ->where('mix_pool_key', $poolKey)
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->withSum('shares', 'quantity_made')
            ->orderBy('ended_at')
            ->orderBy('id')
            ->get()
            ->each(fn (ManufactureTaskSession $session) => $session->leftover_kg = round((float) $session->quantity_made - (float) $session->shares_sum_quantity_made, 3));
    }

    /**
     * Open lines whose step makes this mix, or this strand's pieces, and still asks for some, in plan order.
     *
     * @return Collection<int, JobOrderItemTask>
     */
    public function tasksNeeding(int $productionId, string $poolKey): Collection
    {
        [$manufactureTaskId, $label] = explode('|', self::isMakingKey($poolKey) ? substr($poolKey, strlen(self::MAKING)) : $poolKey);

        return JobOrderItemTask::query()
            ->select('job_order_item_tasks.*')
            ->join('job_orders', 'job_orders.id', 'job_order_item_tasks.job_order_id')
            ->join('job_order_items', 'job_order_items.id', 'job_order_item_tasks.job_order_item_id')
            ->where('job_order_item_tasks.production_id', $productionId)
            ->where('job_order_item_tasks.manufacture_task_id', $manufactureTaskId)
            ->whereRaw("lower(trim(coalesce(job_order_item_tasks.label, ''))) = ?", [$label])
            ->whereNotNull('job_order_item_tasks.artefact_manufacture_task_id')
            ->where('job_order_item_tasks.state', '!=', JobOrderItemTaskStateEnum::DONE->value)
            ->whereColumn('job_order_item_tasks.quantity_required', '>', 'job_order_item_tasks.quantity_made')
            ->whereIn('job_orders.state', array_map(fn (JobOrderStateEnum $state) => $state->value, JobOrderStateEnum::open()))
            ->whereNull('job_orders.data->cancellation')
            ->whereNull('job_order_items.data->carried_to')
            ->orderByRaw('job_orders.planned_for asc nulls last, job_orders.plan_position asc nulls last')
            ->orderBy('job_order_item_tasks.job_order_id')
            ->orderBy('job_order_item_tasks.id')
            ->with(['recipeStep.rawMaterials', 'recipeStep.artefact.recipeSteps.manufactureTask', 'recipeStep.artefact.recipeSteps.rawMaterials', 'manufactureTask'])
            ->get()
            ->filter(fn (JobOrderItemTask $task) => self::poolKey($task) === $poolKey)
            ->values();
    }

    private function stillNeeds(JobOrderItemTask $task): float
    {
        return max(0, round((float) $task->quantity_required - (float) $task->quantity_made, 3));
    }

    /**
     * A strand's mix is always locked before its Making, whichever is asked first, so two closes never wait on each other.
     */
    private function lock(string $poolKey): void
    {
        if (self::isMakingKey($poolKey)) {
            $this->lock(self::mixKeyOf($poolKey));
        }
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['mix_pool:'.$poolKey]);
    }
}
