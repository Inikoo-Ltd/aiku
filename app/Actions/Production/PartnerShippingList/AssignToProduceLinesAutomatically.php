<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Actions\Production\Artisan\PickDepartmentArtisans;
use App\Actions\Production\JobOrder\CancelJobOrder;
use App\Actions\Production\JobOrder\FillToOrder;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\Ordering\Order;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
use App\Models\Production\ManufactureTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4670: an order's To Produce lines whose product has a step set to "assign automatically" (Filling – to order)
 * become a job for that artisan, or the least busy artisan of that work area, as soon as the order is submitted:
 * one job per order, so the tablet lists them order by order, oldest first. Products made in batches of more than one stay
 * on the board, where a small order hitchhikes until a batch fills.
 */
class AssignToProduceLinesAutomatically
{
    use AsAction;

    /**
     * @param  array<int, int>|null  $lineIds  only these lines (after an order edit), all the order's open lines otherwise
     * @param  Carbon|null  $date  the date of the jobs these lines were on, so a remade job keeps its place on the tablet
     * @return array<int, JobOrder>
     */
    public function handle(Order $order, ?array $lineIds = null, ?Carbon $date = null): array
    {
        if ($lineIds === [] || !ManufactureTask::where('organisation_id', $order->organisation_id)->whereNotNull('data->auto_assign')->exists()) {
            return [];
        }

        $lines = PartnerShoppingListItem::whereIn('transaction_id', $order->transactions()->select('id'))
            ->when($lineIds !== null, fn ($query) => $query->whereIn('id', $lineIds))
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->whereNull('job_order_id')
            ->whereNull('pre_picked_at')
            ->with(['transaction.model', 'orgStock'])
            ->get();

        $surplus = GetProductionSurplusInPipeline::run($lines->pluck('org_stock_id')->filter()->unique()->values()->all());

        $groups = [];
        foreach ($lines as $line) {
            $artefact = $this->artefactFor($line);
            $step     = $artefact?->manufactureTasks->first(fn (ManufactureTask $task) => Arr::has($task->data, 'auto_assign'));
            if (!$step || $this->isForThePlanner($line, $artefact, $surplus)) {
                continue;
            }

            $production = $artefact->production;
            $employeeId = $this->assigneeFor($production, $step->data['auto_assign']);
            if ($employeeId) {
                $groups[$production->id.'|'.$employeeId] ??= ['production' => $production, 'employee_id' => $employeeId, 'ids' => []];
                $groups[$production->id.'|'.$employeeId]['ids'][] = $line->id;
            }
        }

        $jobOrders = [];
        foreach ($groups as $group) {
            foreach (StoreJobOrdersFromToProduceItems::make()->handle($group['production'], $group['ids'], $group['employee_id'])['job_orders'] as $jobOrder) {
                $jobOrder->update(array_filter([
                    'data' => array_merge($jobOrder->data ?? [], ['auto_assigned' => true]),
                    'date' => $date,
                ]));
                $jobOrders[] = $jobOrder;
            }
        }

        return $jobOrders;
    }

    /**
     * Lines the board must still decide: a stock made in batches (small orders hitchhike), a stock production already
     * makes or holds more of than its jobs need (the board nets it), and a fill line a manager sent back to the backlog.
     *
     * @param  array<int, array{pending_booking: float, in_production: float}>  $surplus
     */
    private function isForThePlanner(PartnerShoppingListItem $line, Artefact $artefact, array $surplus): bool
    {
        if ($artefact->org_stock_id) {
            return $artefact->recommended_batch_size > 1 || isset($surplus[$line->org_stock_id]);
        }

        return JobOrderItem::where('data->fill->to_produce_id', $line->id)
            ->whereHas('jobOrder', fn ($query) => $query->whereNotNull('data->cancellation->user_id'))
            ->exists();
    }

    /**
     * An order edited or cancelled after its lines went to a job: a job made automatically that holds a line of these
     * order lines and that nobody has started goes back to the backlog, so its lines follow the order again. With
     * $onlyWholeJobs, a job also holding lines of other order lines is left alone.
     *
     * @param  array<int, int>  $transactionIds
     * @return array{line_ids: array<int, int>, date: Carbon|null}
     */
    public function takeBackUntouched(Order $order, array $transactionIds, bool $onlyWholeJobs = false): array
    {
        $jobOrders = JobOrder::whereIn('id', PartnerShoppingListItem::whereIn('transaction_id', $transactionIds)->whereNotNull('job_order_id')->select('job_order_id'))
            ->where('data->auto_assigned', true)
            ->whereIn('state', JobOrderStateEnum::open())
            ->whereNull('data->cancellation')
            ->get()
            ->reject(fn (JobOrder $jobOrder) => ManufactureTaskSession::where('job_order_id', $jobOrder->id)
                ->whereIn('state', [ManufactureTaskSessionStateEnum::OPEN, ManufactureTaskSessionStateEnum::CLOSED])
                ->exists())
            ->reject(fn (JobOrder $jobOrder) => $onlyWholeJobs && PartnerShoppingListItem::where('job_order_id', $jobOrder->id)
                ->where(fn ($query) => $query->whereNull('transaction_id')->orWhereNotIn('transaction_id', $transactionIds))
                ->exists());

        $lineIds = [];
        $date    = null;
        foreach ($jobOrders as $jobOrder) {
            $jobLineIds = PartnerShoppingListItem::where('job_order_id', $jobOrder->id)->pluck('id')->all();
            try {
                CancelJobOrder::make()->action($jobOrder, ['mode' => 'backlog', 'kind' => 'other', 'reason' => __('Order :reference changed', ['reference' => $order->reference])]);
            } catch (ValidationException) {
                continue;
            }
            $lineIds = array_merge($lineIds, $jobLineIds);
            $jobDate = Carbon::parse($jobOrder->date);
            $date    = $date && $date->lte($jobDate) ? $date : $jobDate;
        }

        return ['line_ids' => $lineIds, 'date' => $date];
    }

    private function artefactFor(PartnerShoppingListItem $line): ?Artefact
    {
        $artefact = FillToOrder::make()->artefactFor($line->transaction?->model)
            ?? Artefact::where('organisation_id', $line->organisation_id)
                ->where('org_stock_id', $line->org_stock_id)
                ->whereIn('state', ArtefactStateEnum::launched())
                ->first();

        return $artefact?->load(['manufactureTasks', 'production']);
    }

    /** @param  array{type: string, id: int}  $autoAssign */
    private function assigneeFor(Production $production, array $autoAssign): ?int
    {
        if ($autoAssign['type'] === 'work_area') {
            return PickDepartmentArtisans::make()->forWorkArea($production, $autoAssign['id']);
        }

        return Employee::where('id', $autoAssign['id'])->where('state', EmployeeStateEnum::WORKING)->value('id');
    }
}
