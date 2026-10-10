<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetJobOrderDestinationAllocation
{
    use AsAction;

    /**
     * Splits what the artisan has made across the destinations the to-produce lines ask for.
     * Destinations are filled whole, biggest first, so the warehouse walks to a bay once with a
     * complete quantity instead of dribbling partials into every bay; only the leftover is short.
     * Anything made beyond what the lines asked for goes to normal stock.
     *
     * A line carries the buyer's org stock and the artefact the maker's, so they only meet on the stock.
     *
     * Lines are denominated in SKOs, artisans work in artefact units, org_stocks.packed_in is the
     * only bridge; every quantity returned here is in artefact units.
     *
     * @param  array<int, float>  $madeByItem  artefact units per job order item, when the caller knows better than the last task
     * @return array<int, array{item: JobOrderItem, line: PartnerShoppingListItem|null, location_id: int|null, quantity: float}>
     */
    public function handle(JobOrder $jobOrder, array $madeByItem = []): array
    {
        $items = $jobOrder->jobOrderItems()->with(['artefact.orgStock.stock', 'artefact.orgStock.orgStockFamily', 'tasks'])->get();

        $lines = PartnerShoppingListItem::with('stock')->where('job_order_id', $jobOrder->id)
            ->get()
            ->groupBy('stock_id');

        $bays = OrgPartner::where('organisation_id', $jobOrder->organisation_id)
            ->whereNotNull('goods_out_location_id')
            ->get()
            ->keyBy('partner_id');

        $remainingUnitsByLine = [];
        $allocations          = [];

        foreach ($items as $item) {
            $packedIn = max(1, (int) $item->artefact->orgStock?->packed_in);
            $made     = $madeByItem[$item->id] ?? min((float) $item->quantity, $this->producedArtefacts($item));

            if ($made <= 0) {
                continue;
            }

            $itemLines = $lines->get($item->artefact->orgStock?->stock_id, collect());
            foreach ($itemLines as $line) {
                $remainingUnitsByLine[$line->id] ??= round((float) ($line->quantity_to_produce ?? $line->quantity) * $packedIn);
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

        return array_values(array_filter($allocations, fn (array $allocation) => $allocation['quantity'] > 0));
    }

    /**
     * Whole artefacts the last task has produced; the paperwork never counts a fraction of one.
     */
    public function producedArtefacts(JobOrderItem $item): float
    {
        $lastTask = $item->tasks->last();

        if (!$lastTask) {
            return 0.0;
        }

        $unitsPerArtefact = (float) ArtefactManufactureTask::where('artefact_id', $item->artefact_id)
            ->where('manufacture_task_id', $lastTask->manufacture_task_id)
            ->value('units_per_artefact') ?: 1;

        return floor((float) $lastTask->quantity_made / $unitsPerArtefact);
    }
}
