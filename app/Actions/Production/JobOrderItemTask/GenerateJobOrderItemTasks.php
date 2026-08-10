<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItemTask;

use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Models\Production\JobOrderItem;
use App\Models\Production\JobOrderItemTask;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateJobOrderItemTasks
{
    use AsAction;

    /**
     * @return array<int, JobOrderItemTask>
     */
    public function handle(JobOrderItem $jobOrderItem): array
    {
        $jobOrder = $jobOrderItem->jobOrder;
        $tasks    = [];

        foreach ($jobOrderItem->artefact->manufactureTasks as $manufactureTask) {
            $tasks[] = JobOrderItemTask::firstOrCreate(
                [
                    'job_order_item_id'   => $jobOrderItem->id,
                    'manufacture_task_id' => $manufactureTask->id,
                ],
                [
                    'group_id'          => $jobOrderItem->group_id,
                    'organisation_id'   => $jobOrderItem->organisation_id,
                    'production_id'     => $jobOrder->production_id,
                    'job_order_id'      => $jobOrder->id,
                    'state'             => JobOrderItemTaskStateEnum::TODO,
                    'position'          => $manufactureTask->pivot->position,
                    'quantity_required' => $jobOrderItem->quantity * $manufactureTask->pivot->units_per_artefact,
                ]
            );
        }

        return $tasks;
    }
}
