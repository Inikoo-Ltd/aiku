<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Actions\Production\JobOrder\GetJobOrderDestinationAllocation;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\JobOrderItem;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProductionSurplusInPipeline
{
    use AsAction;

    /**
     * What open job orders make beyond the lines they were made for, in SKOs, so a new order for the
     * same stock is not made twice while that surplus waits to be booked in.
     * Pending booking is made and not yet in stock; in production is still being made.
     *
     * @param  array<int, int>  $orgStockIds
     * @return array<int, array{pending_booking: float, in_production: float, job_orders: array<int, string>}>
     */
    public function handle(array $orgStockIds): array
    {
        if (!$orgStockIds) {
            return [];
        }

        $items = JobOrderItem::query()
            ->join('artefacts', 'artefacts.id', 'job_order_items.artefact_id')
            ->join('job_orders', 'job_orders.id', 'job_order_items.job_order_id')
            ->whereIn('artefacts.org_stock_id', $orgStockIds)
            ->whereIn('job_orders.state', JobOrderStateEnum::open())
            ->select('job_order_items.*', 'artefacts.org_stock_id', 'job_orders.reference as job_order_reference')
            ->with(['artefact.orgStock', 'tasks'])
            ->get();

        $surplus = [];

        foreach ($items as $item) {
            $packedIn = max(1, (int) $item->artefact->orgStock?->packed_in);
            $claimed  = $this->claimedSkos($item);
            $made     = max(0, min((float) $item->quantity, GetJobOrderDestinationAllocation::make()->producedArtefacts($item)) / $packedIn - $claimed);
            $planned  = max(0, (float) $item->quantity / $packedIn - $claimed);
            $received = $this->surplusReceived($item, (float) $item->quantity_received);

            $pendingBooking = max(0, $made - $received);
            $inProduction   = max(0, $planned - max($made, $received));

            if ($pendingBooking + $inProduction <= 0) {
                continue;
            }

            $row                    = $surplus[$item->org_stock_id] ?? ['pending_booking' => 0.0, 'in_production' => 0.0, 'job_orders' => []];
            $row['pending_booking'] = round($row['pending_booking'] + $pendingBooking, 3);
            $row['in_production']   = round($row['in_production'] + $inProduction, 3);
            $row['job_orders'][]    = $item->job_order_reference;

            $surplus[$item->org_stock_id] = $row;
        }

        return $surplus;
    }

    /**
     * SKOs of this item already received into stock beyond what its own lines claimed.
     * Claims are filled first, so surplus only starts once they are covered.
     */
    public function surplusReceived(JobOrderItem $item, float $receivedUnits): float
    {
        $packedIn = max(1, (int) $item->artefact->orgStock?->packed_in);

        return max(0, $receivedUnits / $packedIn - $this->claimedSkos($item));
    }

    /**
     * A partner line takes everything it asked to be made to its bay; an own customer line only
     * needs what its order asked for, anything made on top is stock.
     */
    private function claimedSkos(JobOrderItem $item): float
    {
        return (float) PartnerShoppingListItem::where('job_order_id', $item->job_order_id)
            ->where('stock_id', $item->artefact->orgStock?->stock_id)
            ->get()
            ->sum(fn (PartnerShoppingListItem $line) => $line->partner_organisation_id
                ? (float) ($line->quantity_to_produce ?? $line->quantity)
                : (float) $line->quantity);
    }
}
