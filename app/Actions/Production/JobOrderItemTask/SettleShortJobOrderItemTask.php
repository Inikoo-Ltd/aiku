<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 17:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItemTask;

use App\Actions\Production\JobOrder\ConfirmJobOrder;
use App\Actions\Production\JobOrder\GetJobOrderDestinationAllocation;
use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\JobOrderItem\StoreJobOrderItem;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
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
            $unitsPerArtefact = $this->unitsPerArtefact($item, $jobOrderItemTask);

            $madeArtefacts      = (int) floor($jobOrderItemTask->quantity_made / $unitsPerArtefact);
            $remainingArtefacts = max(0, $item->quantity - $madeArtefacts);

            $excessUnitsByTask = [];
            $item->update(['quantity' => $madeArtefacts]);
            foreach ($item->tasks as $task) {
                $units    = $this->unitsPerArtefact($item, $task);
                $required = min((float) $task->quantity_required, $madeArtefacts * $units);
                $task->update(['quantity_required' => $required]);
                CalculateJobOrderItemTaskQuantities::run($task);
                $excessUnitsByTask[$task->manufacture_task_id] = max(0, (float) $task->quantity_made - $required);
            }

            if (!$carryOver || $remainingArtefacts <= 0) {
                return null;
            }

            $original = $item->jobOrder;
            $carried  = StoreJobOrder::make()->action($original->production, [
                'employee_id' => $original->employee_id,
            ]);
            $carriedItem = StoreJobOrderItem::make()->action($carried, [
                'artefact_id' => $item->artefact_id,
                'quantity'    => $remainingArtefacts,
            ]);
            $this->creditWorkAlreadyDone($carriedItem, $excessUnitsByTask);
            ConfirmJobOrder::make()->action($carried);

            $this->carryLines($original, $carried, $item, $madeArtefacts);

            return $carried;
        });
    }

    /**
     * Earlier steps may have been worked (and paid) beyond what the closing step finished; that
     * work exists and must not be asked for, or paid, again on the carried job.
     *
     * @param  array<int, float>  $excessUnitsByTask
     */
    private function creditWorkAlreadyDone(JobOrderItem $carriedItem, array $excessUnitsByTask): void
    {
        foreach ($carriedItem->tasks as $task) {
            $excess = $excessUnitsByTask[$task->manufacture_task_id] ?? 0;
            if ($excess <= 0) {
                continue;
            }
            $task->update(['quantity_required' => max(0, (float) $task->quantity_required - $excess)]);
            CalculateJobOrderItemTaskQuantities::run($task);
        }
    }

    /**
     * The lines the made goods already cover stay with the original job order, so the warehouse
     * still knows where to walk them; only what is still owed follows the carried job order.
     * Lines count SKOs, the allocation counts artefact units, packed_in bridges.
     */
    private function carryLines(JobOrder $original, JobOrder $carried, JobOrderItem $item, int $madeArtefacts): void
    {
        $packedIn = max(1, (int) $item->artefact->orgStock?->packed_in);

        $coveredUnits = [];
        foreach (GetJobOrderDestinationAllocation::run($original, [$item->id => (float) $madeArtefacts]) as $allocation) {
            if ($allocation['line'] && $allocation['item']->id === $item->id) {
                $coveredUnits[$allocation['line']->id] = ($coveredUnits[$allocation['line']->id] ?? 0) + $allocation['quantity'];
            }
        }

        $lines = PartnerShoppingListItem::where('job_order_id', $original->id)
            ->where('org_stock_id', $item->artefact->org_stock_id)
            ->get();

        foreach ($lines as $line) {
            $wantedSkos  = (float) ($line->quantity_to_produce ?? $line->quantity);
            $coveredSkos = round(($coveredUnits[$line->id] ?? 0) / $packedIn, 3);

            if ($coveredSkos <= 0) {
                $line->update(['job_order_id' => $carried->id]);
                continue;
            }

            if ($coveredSkos >= $wantedSkos) {
                continue;
            }

            PartnerShoppingListItem::create([
                ...$line->only([
                    'group_id',
                    'organisation_id',
                    'org_partner_id',
                    'partner_organisation_id',
                    'stock_id',
                    'org_stock_id',
                    'priority',
                    'needed_by',
                    'notes',
                    'added_by_user_id',
                    'state',
                ]),
                'parent_id'           => $line->id,
                'quantity'            => round($wantedSkos - $coveredSkos, 3),
                'quantity_to_produce' => round($wantedSkos - $coveredSkos, 3),
                'job_order_id'        => $carried->id,
                'created_at'          => $line->created_at,
            ]);

            $line->update([
                'quantity'            => min((float) $line->quantity, $coveredSkos),
                'quantity_to_produce' => $coveredSkos,
            ]);
        }
    }

    private function unitsPerArtefact(JobOrderItem $item, JobOrderItemTask $task): float
    {
        return (float) ArtefactManufactureTask::where('artefact_id', $item->artefact_id)
            ->where('manufacture_task_id', $task->manufacture_task_id)
            ->value('units_per_artefact') ?: 1;
    }
}
