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
 * A mix is shared by every open line that uses it, whatever the product or job order (HELP-4562).
 * A mix recorded on one line goes to the lines that need it, that line first and then in plan order;
 * what no line needs stays on the session as leftover, given first to the next lines that need the mix.
 * The leftover is never stored: it is the session's kg less its shares.
 */
class ShareMixPool
{
    use AsObject;

    private const float EPSILON = 0.0005;

    public static function poolKey(JobOrderItemTask $task): ?string
    {
        if (!$task->isRecordedInKg() || !$task->recipeStep) {
            return null;
        }

        return $task->recipeStep->loadMissing('rawMaterials')->mixPoolKey();
    }

    /**
     * @return Collection<int, ManufactureTaskSessionShare> keyed by task id
     */
    /**
     * @param  Collection<int, JobOrderItemTask>|null  $firstTo  lines served before the plan order, as a corrected mix goes back to the lines it went to
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
        }
        $session->unsetRelation('shares');

        return $shares;
    }

    /**
     * Gives the leftover of earlier mixes to the open lines still waiting for this mix, oldest mix first.
     */
    /**
     * @param  array<int, int>  $exceptTaskIds  lines leaving their job, never given mix
     */
    public function fill(int $productionId, string $poolKey, array $exceptTaskIds = []): void
    {
        $this->lock($poolKey);

        $leftovers = $this->leftovers($productionId, $poolKey);
        if ($leftovers->isEmpty()) {
            return;
        }

        foreach ($this->tasksNeeding($productionId, $poolKey)->whereNotIn('id', $exceptTaskIds) as $task) {
            $need = $this->stillNeeds($task);
            foreach ($leftovers as $leftover) {
                if ($need <= self::EPSILON) {
                    break;
                }
                $given = round(min($need, $leftover->leftover_kg), 3);
                if ($given <= self::EPSILON) {
                    continue;
                }
                $share = ManufactureTaskSessionShare::firstOrNew(['manufacture_task_session_id' => $leftover->id, 'job_order_item_task_id' => $task->id], ['share' => 0, 'quantity_rejected' => 0, 'quantity_made' => 0]);
                $share->quantity_made = round((float) $share->quantity_made + $given, 3);
                $share->share         = round((float) $share->quantity_made / (float) $leftover->quantity_made, 6);
                $share->save();

                $leftover->leftover_kg = round($leftover->leftover_kg - $given, 3);
                $need                  = round($need - $given, 3);
            }
            if ($need < $this->stillNeeds($task)) {
                CalculateJobOrderItemTaskQuantities::run($task);
            }
            $leftovers = $leftovers->filter(fn (ManufactureTaskSession $leftover) => $leftover->leftover_kg > self::EPSILON);
            if ($leftovers->isEmpty()) {
                return;
            }
        }
    }

    /**
     * A line asking for less of a mix than it was given hands the difference back to the mix, newest mix first,
     * so the next line that needs it gets it. A line carried to another job keeps it: its order credits it
     * to the carried part (HELP-4509).
     */
    public function settle(JobOrderItemTask $task): void
    {
        $poolKey = self::poolKey($task);
        if (!$poolKey) {
            return;
        }

        DB::transaction(function () use ($task, $poolKey) {
            $this->lock($poolKey);
            $excess = round((float) $task->quantity_made - (float) $task->quantity_required, 3);
            if ($excess > self::EPSILON && !Arr::has($task->jobOrderItem->data ?? [], 'carried_to')) {
                $this->giveBack($task, $excess);
            }
            $this->fill($task->production_id, $poolKey);
        });
    }

    /**
     * A line leaving its job before any of its mix was used hands all of it back to the mix.
     *
     * @param  iterable<JobOrderItemTask>  $tasks
     */
    public function giveBackAll(iterable $tasks): void
    {
        $leavingIds = collect($tasks)->pluck('id')->all();
        foreach ($tasks as $task) {
            $poolKey = self::poolKey($task);
            if (!$poolKey) {
                continue;
            }
            DB::transaction(function () use ($task, $poolKey, $leavingIds) {
                $this->lock($poolKey);
                $this->giveBack($task, INF);
                $this->fill($task->production_id, $poolKey, $leavingIds);
            });
        }
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
     * @return Collection<int, ManufactureTaskSession> with leftover_kg, oldest first
     */
    public function leftovers(int $productionId, string $poolKey): Collection
    {
        return ManufactureTaskSession::where('production_id', $productionId)
            ->where('mix_pool_key', $poolKey)
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->where(fn ($query) => $query->whereNull('mix_use_by')->orWhere('mix_use_by', '>', now()))
            ->withSum('shares', 'quantity_made')
            ->orderBy('ended_at')
            ->orderBy('id')
            ->get()
            ->each(fn (ManufactureTaskSession $session) => $session->leftover_kg = round((float) $session->quantity_made - (float) $session->shares_sum_quantity_made, 3))
            ->filter(fn (ManufactureTaskSession $session) => $session->leftover_kg > self::EPSILON)
            ->values();
    }

    /**
     * Open lines whose step makes this mix and still asks for some, in plan order.
     *
     * @return Collection<int, JobOrderItemTask>
     */
    public function tasksNeeding(int $productionId, string $poolKey): Collection
    {
        [$manufactureTaskId, $label] = explode('|', $poolKey);

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
            ->with(['recipeStep.rawMaterials', 'manufactureTask'])
            ->get()
            ->filter(fn (JobOrderItemTask $task) => self::poolKey($task) === $poolKey)
            ->values();
    }

    private function stillNeeds(JobOrderItemTask $task): float
    {
        return max(0, round((float) $task->quantity_required - (float) $task->quantity_made, 3));
    }

    private function lock(string $poolKey): void
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['mix_pool:'.$poolKey]);
    }
}
