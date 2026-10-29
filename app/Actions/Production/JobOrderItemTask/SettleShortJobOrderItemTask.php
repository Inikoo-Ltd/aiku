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
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
use App\Models\Production\JobOrderItemTask;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            $madeArtefacts      = (int) $jobOrderItemTask->artefactsIn();
            $remainingArtefacts = max(0, $item->quantity - $madeArtefacts);

            if ($madeArtefacts < 1) {
                if ($carryOver) {
                    return null;
                }
                throw ValidationException::withMessages([
                    'quantity_made' => __('Nothing has been finished on this job yet, it cannot be closed as finished'),
                ]);
            }

            $excessUnitsByTask = [];
            $item->update(['quantity' => $madeArtefacts]);
            foreach ($item->tasks as $task) {
                $required = min((float) $task->quantity_required, $task->requiredFor($madeArtefacts));
                $task->setRequiredQuantity($required);
                $excessUnitsByTask[$task->manufacture_task_id] = max(0, (float) $task->quantity_made - $required);
            }

            if (!$carryOver || $remainingArtefacts <= 0) {
                return null;
            }

            $original = $item->jobOrder;
            $root     = (int) Arr::get($original->data, 'carried_from', $original->id);
            $carried  = StoreJobOrder::make()->action($original->production, [
                'employee_id'   => $item->employee_id ?? $original->employee_id,
                'reference'     => $this->carriedReference($original),
                'planned_for'   => $original->planned_for?->toDateString(),
                'plan_position' => $original->plan_position,
            ]);
            $carried->update(['data' => array_merge($carried->data, ['carried_from' => $root], $original->isTrialRun() ? ['trial_run' => true] : [])]);
            $carriedItem = StoreJobOrderItem::make()->action($carried, [
                'artefact_id' => $item->artefact_id,
                'quantity'    => $remainingArtefacts,
            ]);
            $item->update(['data' => array_merge($item->data ?? [], ['carried_to' => $carried->id])]);
            foreach ($carriedItem->tasks as $carriedTask) {
                $carriedTask->update(['employee_id' => $item->tasks->firstWhere('manufacture_task_id', $carriedTask->manufacture_task_id)?->employee_id]);
            }
            $this->creditWorkAlreadyDone($carriedItem, $excessUnitsByTask);
            ConfirmJobOrder::make()->action($carried);

            $this->carryLines($original, $carried, $item, $madeArtefacts);

            return $carried;
        });
    }

    /**
     * A job carried to the next day keeps its number: the rest is the same reference with the
     * next letter, so goods in read JOaroma-0002 and JOaroma-0002a as one job, not two.
     */
    public function carriedReference(JobOrder $original): string
    {
        $root    = (int) Arr::get($original->data, 'carried_from', $original->id);
        $base    = self::baseReference($original);
        $carried = JobOrder::withTrashed()->where('data->carried_from', $root)->count();

        return $base.chr(ord('a') + min($carried, 25));
    }

    /**
     * What closing this step short and carrying the rest would do, for the floor to say before it is done.
     *
     * @return array{reference: string, units_per_artefact: float, quantity: int}
     */
    public function preview(JobOrderItemTask $jobOrderItemTask): array
    {
        return [
            'reference'          => $this->carriedReference($jobOrderItemTask->jobOrder),
            'units_per_artefact' => $jobOrderItemTask->unitsPerArtefact(),
            'quantity'           => (int) $jobOrderItemTask->jobOrderItem->quantity,
        ];
    }

    public static function baseReference(JobOrder $jobOrder): string
    {
        return preg_replace('/[a-z]+$/', '', (string) $jobOrder->reference);
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
        $packedIn = $item->artefact->packedIn();

        $plannedByItem = $original->jobOrderItems()
            ->where('artefact_id', $item->artefact_id)
            ->pluck('quantity', 'id')
            ->map(fn ($quantity) => (float) $quantity)
            ->all();
        $plannedByItem[$item->id] = (float) $madeArtefacts;

        $coveredUnits = [];
        foreach (GetJobOrderDestinationAllocation::run($original, $plannedByItem) as $allocation) {
            if ($allocation['line'] && $allocation['item']->artefact_id === $item->artefact_id) {
                $coveredUnits[$allocation['line']->id] = ($coveredUnits[$allocation['line']->id] ?? 0) + $allocation['quantity'];
            }
        }

        $lines = PartnerShoppingListItem::where('job_order_id', $original->id)
            ->where('stock_id', $item->artefact->orgStock?->stock_id)
            ->get();

        foreach ($lines as $line) {
            $line->moveUncoveredTo(round(($coveredUnits[$line->id] ?? 0) / $packedIn, 3), $carried->id);
        }
    }
}
