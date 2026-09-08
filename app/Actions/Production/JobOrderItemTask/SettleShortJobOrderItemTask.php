<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 17:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItemTask;

use App\Actions\Production\JobOrder\ConfirmJobOrder;
use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\JobOrderItem\StoreJobOrderItem;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItemTask;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SettleShortJobOrderItemTask
{
    use AsAction;

    /**
     * Closes the job order item at what was actually made. With $carryOver the shortfall
     * is re-issued as a new confirmed job order for the same artisan.
     */
    public function handle(JobOrderItemTask $jobOrderItemTask, bool $carryOver): ?JobOrder
    {
        return DB::transaction(function () use ($jobOrderItemTask, $carryOver) {
            $item             = $jobOrderItemTask->jobOrderItem;
            $unitsPerArtefact = (float) ArtefactManufactureTask::where('artefact_id', $item->artefact_id)
                ->where('manufacture_task_id', $jobOrderItemTask->manufacture_task_id)
                ->value('units_per_artefact') ?: 1;

            $madeArtefacts      = (int) floor($jobOrderItemTask->quantity_made / $unitsPerArtefact);
            $remainingArtefacts = max(0, $item->quantity - $madeArtefacts);

            $item->update(['quantity' => $madeArtefacts]);
            foreach ($item->tasks as $task) {
                $units = (float) ArtefactManufactureTask::where('artefact_id', $item->artefact_id)
                    ->where('manufacture_task_id', $task->manufacture_task_id)
                    ->value('units_per_artefact') ?: 1;
                $task->update(['quantity_required' => min((float) $task->quantity_required, $madeArtefacts * $units)]);
                CalculateJobOrderItemTaskQuantities::run($task);
            }

            if (!$carryOver || $remainingArtefacts <= 0) {
                return null;
            }

            $original = $item->jobOrder;
            $carried  = StoreJobOrder::make()->action($original->production, [
                'employee_id' => $original->employee_id,
            ]);
            StoreJobOrderItem::make()->action($carried, [
                'artefact_id' => $item->artefact_id,
                'quantity'    => $remainingArtefacts,
            ]);
            ConfirmJobOrder::make()->action($carried);

            PartnerShoppingListItem::where('job_order_id', $original->id)->update(['job_order_id' => $carried->id]);

            return $carried;
        });
    }
}
