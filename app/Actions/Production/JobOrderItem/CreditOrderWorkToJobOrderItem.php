<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItem;

use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\JobOrderItem;
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
     * A line on its own simply follows the recipe.
     */
    public function handle(JobOrderItem $jobOrderItem): JobOrderItem
    {
        $surplusByStep = [];
        foreach ($jobOrderItem->orderItems()->reject(fn (JobOrderItem $item) => $item->id == $jobOrderItem->id) as $item) {
            $isStillMaking = !Arr::has($item->data ?? [], 'carried_to') && in_array($item->jobOrder->state, JobOrderStateEnum::open());
            foreach ($item->tasks as $step) {
                $stillToMake = $isStillMaking ? max(0, (float) $step->quantity_required - (float) $step->quantity_made) : 0;
                $surplusByStep[$step->manufacture_task_id] = ($surplusByStep[$step->manufacture_task_id] ?? 0)
                    + (float) $step->quantity_made - max(0, $step->requiredFor($item->quantity) - $stillToMake);
            }
        }

        foreach ($jobOrderItem->tasks as $task) {
            $surplus = max(0, $surplusByStep[$task->manufacture_task_id] ?? 0);
            $floor   = $task->isRecordedInKg() ? 0 : (float) $task->quantity_made;
            $task->setRequiredQuantity(max($floor, round($task->requiredFor($jobOrderItem->quantity) - $surplus, 3)));
        }

        return $jobOrderItem;
    }
}
