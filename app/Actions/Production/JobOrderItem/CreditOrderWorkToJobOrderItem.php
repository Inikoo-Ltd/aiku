<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItem;

use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Actions\Production\JobOrderItemTask\ShareMixPool;
use App\Models\Production\JobOrderItem;
use App\Models\Production\JobOrderItemTask;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class CreditOrderWorkToJobOrderItem
{
    use AsAction;

    /**
     * Sets what each step of the line still asks for, counting the work the rest of its order made
     * beyond its own needs (HELP-4509): mix made in surplus on an earlier part of the order is not
     * asked for, and does not lock the later steps, again. The surplus is what the order made on the
     * step less what every other part takes from it: all its recipe needs, less what it will still
     * make itself. A line carried on or no longer open makes nothing more, so it takes all its needs.
     * A line on its own simply follows the recipe. Pieces the order already has past a step are made,
     * so the steps that step waits for are not asked for them either, even when the mix recorded in kg
     * came out under the recipe (HELP-4847).
     */
    public function handle(JobOrderItem $jobOrderItem): JobOrderItem
    {
        $surplusByStep = [];
        foreach ($jobOrderItem->orderItems()->reject(fn (JobOrderItem $item) => $item->id == $jobOrderItem->id) as $item) {
            $isStillMaking = !Arr::has($item->data ?? [], 'carried_to') && in_array($item->jobOrder->state, JobOrderStateEnum::open());
            foreach ($item->tasks as $step) {
                $stillToMake = $isStillMaking ? max(0, (float) $step->quantity_required - (float) $step->quantity_made) : 0;
                $surplusByStep[$step->stepKey()] = ($surplusByStep[$step->stepKey()] ?? 0)
                    + (float) $step->quantity_made - max(0, $step->requiredFor($item->quantity) - $stillToMake);
            }
        }

        $steps            = $jobOrderItem->tasks->sortByDesc(fn (JobOrderItemTask $task) => [$task->position, $task->id])->values();
        $coveredArtefacts = [];
        foreach ($steps as $task) {
            $coveredArtefacts[$task->id] = $steps
                ->filter(fn (JobOrderItemTask $later) => isset($coveredArtefacts[$later->id]) && $later->awaits($task))
                ->reduce(fn (float $covered, JobOrderItemTask $later) => max($covered, $coveredArtefacts[$later->id]), $task->isRecordedInKg() ? 0.0 : $task->artefactsIn(max(0, $surplusByStep[$task->stepKey()] ?? 0)));
        }

        foreach ($jobOrderItem->tasks as $task) {
            $surplus  = max(0, $surplusByStep[$task->stepKey()] ?? 0);
            $floor    = $task->isRecordedInKg() || ShareMixPool::poolKey($task) ? 0 : (float) $task->quantity_made;
            $required = min(round($task->requiredFor($jobOrderItem->quantity) - $surplus, 3), $task->requiredFor(max(0, $jobOrderItem->quantity - $coveredArtefacts[$task->id])));
            $task->setRequiredQuantity(max($floor, $required));
        }

        return $jobOrderItem;
    }
}
