<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetJobOrderDestinationAllocation
{
    use AsAction;

    private const array ITEM_RELATIONS = ['artefact.orgStock.stock', 'artefact.orgStock.orgStockFamily', 'artefact.manufactureTasks', 'tasks.recipeStep', 'tasks.manufactureTask'];

    /** @var array<int, Collection<int, JobOrderItem>> */
    private array $preloadedItems = [];

    /** @var array<int, Collection<int, Collection<int, PartnerShoppingListItem>>> */
    private array $preloadedLines = [];

    /** @var array<int, Collection<int, JobOrderItem>|null> */
    private array $preloadedCarriedToItems = [];

    /** @var array<int, Collection<int, OrgPartner>> */
    private array $baysByOrganisation = [];

    /**
     * Loads the items, lines and carried jobs of many job orders at once, so a list of open job
     * orders costs a handful of queries instead of dozens per job order. Only for readers: what is
     * loaded here is reused as it is, so never preload before writing to these job orders.
     *
     * @param  Collection<int, JobOrder>  $jobOrders
     * @param  array<int, string>  $itemRelations  extra relations the caller reads on the items
     */
    public function preload(Collection $jobOrders, array $itemRelations = []): static
    {
        $jobOrderIds = $jobOrders->pluck('id')->unique()->values()->all();
        if (!$jobOrderIds) {
            return $this;
        }

        $items = JobOrderItem::whereIn('job_order_id', $jobOrderIds)->with([...self::ITEM_RELATIONS, ...$itemRelations])->get()->groupBy('job_order_id');
        $lines = $this->openLines(PartnerShoppingListItem::with('stock')->whereIn('job_order_id', $jobOrderIds))->groupBy('job_order_id');

        foreach ($jobOrderIds as $jobOrderId) {
            $this->preloadedItems[$jobOrderId] = $items->get($jobOrderId, collect());
            $this->preloadedLines[$jobOrderId] = $lines->get($jobOrderId, collect())->groupBy('stock_id');
        }

        $this->preloadCarriedTo($items->flatten(1));

        return $this;
    }

    /**
     * @param  Collection<int, JobOrderItem>  $items
     */
    private function preloadCarriedTo(Collection $items): void
    {
        while ($carriedToIds = $items->map(fn (JobOrderItem $item) => Arr::get($item->data, 'carried_to'))->filter()->unique()
            ->reject(fn ($id) => array_key_exists($id, $this->preloadedCarriedToItems))->values()->all()) {
            $openJobOrders = JobOrder::whereIn('state', JobOrderStateEnum::open())->whereIn('id', $carriedToIds)->with('jobOrderItems.tasks')->get()->keyBy('id');

            foreach ($carriedToIds as $carriedToId) {
                $this->preloadedCarriedToItems[$carriedToId] = $openJobOrders->get($carriedToId)?->jobOrderItems;
            }

            $items = $openJobOrders->flatMap(fn (JobOrder $jobOrder) => $jobOrder->jobOrderItems);
        }
    }

    private function openLines(Builder $query): Collection
    {
        return $query->where(fn ($query) => $query->whereNull('transaction_id')
            ->orWhereHas('transaction', fn ($query) => $query->whereNotIn('state', [TransactionStateEnum::DISPATCHED, TransactionStateEnum::CANCELLED])))
            ->get();
    }

    /**
     * Splits what the artisan has made across the destinations the to-produce lines ask for.
     * Destinations are filled whole, biggest first, so the warehouse walks to a bay once with a
     * complete quantity instead of dribbling partials into every bay; only the leftover is short.
     * Anything made beyond what the lines asked for goes to normal stock: a partner asking for 50
     * out of a batch of 280 gets 50 in its bay, the other 230 are ours.
     *
     * A line carries the buyer's org stock and the artefact the maker's, so they only meet on the stock.
     * A line whose order has already shipped or been cancelled asks for nothing: its goods go to stock.
     *
     * Lines are denominated in SKOs, artisans work in artefact units, org_stocks.packed_in is the
     * only bridge; every quantity returned here is in artefact units.
     *
     * @param  array<int, float>  $madeByItem  artefact units per job order item, when the caller knows better than the last task
     * @return array<int, array{item: JobOrderItem, line: PartnerShoppingListItem|null, location_id: int|null, quantity: float}>
     */
    public function handle(JobOrder $jobOrder, array $madeByItem = []): array
    {
        $isPreloaded = array_key_exists($jobOrder->id, $this->preloadedItems);

        $items = $isPreloaded ? $this->preloadedItems[$jobOrder->id] : $jobOrder->jobOrderItems()->with(self::ITEM_RELATIONS)->get();
        $lines = $isPreloaded ? $this->preloadedLines[$jobOrder->id] : $this->openLines(PartnerShoppingListItem::with('stock')->where('job_order_id', $jobOrder->id))->groupBy('stock_id');

        $bays = $this->baysByOrganisation[$jobOrder->organisation_id] ??= OrgPartner::where('organisation_id', $jobOrder->organisation_id)
            ->whereNotNull('goods_out_location_id')
            ->get()
            ->keyBy('partner_id');

        $remainingUnitsByLine = [];
        $allocations          = [];

        foreach ($items as $item) {
            $packedIn = $item->artefact->packedIn();
            $made     = $madeByItem[$item->id] ?? min((float) $item->quantity, $this->producedArtefacts($item));

            if ($made <= 0) {
                continue;
            }

            $itemLines = $lines->get($item->artefact->orgStock?->stock_id, collect());
            foreach ($itemLines as $line) {
                $remainingUnitsByLine[$line->id] ??= round($line->claimedSkos() * $packedIn);
            }

            $itemLines = $itemLines->sortByDesc(fn (PartnerShoppingListItem $line) => $remainingUnitsByLine[$line->id]);

            foreach ($itemLines as $line) {
                if ($made <= 0) {
                    break;
                }

                $wanted = min($made, $remainingUnitsByLine[$line->id]);
                if ($wanted <= 0) {
                    continue;
                }

                $made                            -= $wanted;
                $remainingUnitsByLine[$line->id] -= $wanted;

                $allocations[] = [
                    'item'        => $item,
                    'line'        => $line,
                    'location_id' => $line->partner_organisation_id ? $bays->get($line->organisation_id)?->bayIdFor((bool) $line->stock?->is_cosmetic, (bool) $item->artefact->orgStock?->isOnGbPallet()) : null,
                    'quantity'    => $wanted,
                ];
            }

            if ($made > 0) {
                $allocations[] = [
                    'item'        => $item,
                    'line'        => null,
                    'location_id' => null,
                    'quantity'    => $made,
                ];
            }
        }

        return $allocations;
    }

    /**
     * The same split, less what the warehouse has already walked. A walk is remembered against the
     * destination it went to, so the bays can be served in any order; whatever was received
     * before walks were remembered is taken off in allocation order.
     *
     * While the rest of a carried job is still being made only whole packs are walked: the loose
     * artefacts wait at the artisan's desk and go out with the next part, so a bay never gets 12.25.
     *
     * @return array<int, array{item: JobOrderItem, line: PartnerShoppingListItem|null, location_id: int|null, quantity: float}>
     */
    public function outstanding(JobOrder $jobOrder): array
    {
        $allocations = $this->handle($jobOrder);
        $walked      = [];
        $unexplained = [];

        foreach ($allocations as $index => $allocation) {
            $item        = $allocation['item'];
            $destination = $allocation['location_id'] ?? 'stock';

            $unexplained[$item->id]          ??= (float) $item->quantity_received;
            $walked[$item->id][$destination] ??= (float) Arr::get($item->data, 'put_away.'.$destination, 0);

            $putAway = min($allocation['quantity'], $walked[$item->id][$destination], $unexplained[$item->id]);

            $walked[$item->id][$destination] -= $putAway;
            $unexplained[$item->id]          -= $putAway;
            $allocations[$index]['quantity'] -= $putAway;
        }

        foreach ($allocations as $index => $allocation) {
            $itemId  = $allocation['item']->id;
            $putAway = min($allocation['quantity'], $unexplained[$itemId]);

            $unexplained[$itemId]            -= $putAway;
            $allocations[$index]['quantity'] -= $putAway;
        }

        foreach ($allocations as $index => $allocation) {
            $item     = $allocation['item'];
            $packedIn = $item->artefact->packedIn();
            if ($packedIn > 1 && $this->restStillBeingMade($item)) {
                $allocations[$index]['quantity'] = floor(round($allocation['quantity'] / $packedIn, 6)) * $packedIn;
            }
        }

        return array_values(array_filter($allocations, fn (array $allocation) => $allocation['quantity'] > 0));
    }

    private function restStillBeingMade(JobOrderItem $item): bool
    {
        $carriedToId = Arr::get($item->data, 'carried_to');
        $nextItem    = $carriedToId && array_key_exists($carriedToId, $this->preloadedCarriedToItems)
            ? $this->preloadedCarriedToItems[$carriedToId]?->firstWhere('artefact_id', $item->artefact_id)
            : $item->carriedTo()?->jobOrderItems()->with('tasks')->where('artefact_id', $item->artefact_id)->first();
        if (!$nextItem) {
            return false;
        }

        return $nextItem->tasks->contains(fn ($task) => $task->state !== JobOrderItemTaskStateEnum::DONE) || $this->restStillBeingMade($nextItem);
    }

    /**
     * Whole artefacts the last task has produced; the paperwork never counts a fraction of one.
     */
    public function producedArtefacts(JobOrderItem $item): float
    {
        return $item->finalTask()?->artefactsIn() ?? 0.0;
    }
}
