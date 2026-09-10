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
     * @return array<int, array{item: JobOrderItem, line: PartnerShoppingListItem|null, location_id: int|null, quantity: float}>
     */
    public function handle(JobOrder $jobOrder): array
    {
        $items = $jobOrder->jobOrderItems()->with(['artefact', 'tasks'])->get();

        $lines = PartnerShoppingListItem::where('job_order_id', $jobOrder->id)
            ->get()
            ->groupBy('org_stock_id');

        $bays = OrgPartner::where('organisation_id', $jobOrder->organisation_id)
            ->whereNotNull('goods_out_location_id')
            ->pluck('goods_out_location_id', 'partner_id');

        $allocations = [];

        foreach ($items as $item) {
            $made = $this->producedArtefacts($item);

            if ($made <= 0) {
                continue;
            }

            $itemLines = $lines->get($item->artefact->org_stock_id, collect())
                ->sortByDesc(fn (PartnerShoppingListItem $line) => (float) ($line->quantity_to_produce ?? $line->quantity));

            foreach ($itemLines as $line) {
                if ($made <= 0) {
                    break;
                }

                $wanted   = min($made, (float) ($line->quantity_to_produce ?? $line->quantity));
                $made    -= $wanted;

                $allocations[] = [
                    'item'        => $item,
                    'line'        => $line,
                    'location_id' => $line->partner_organisation_id ? $bays->get($line->organisation_id) : null,
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
     * The artisan works in task units, the job order item counts artefacts, units_per_artefact bridges.
     */
    private function producedArtefacts(JobOrderItem $item): float
    {
        $lastTask = $item->tasks->sortByDesc('position')->first();

        if (!$lastTask) {
            return 0.0;
        }

        $unitsPerArtefact = (float) ArtefactManufactureTask::where('artefact_id', $item->artefact_id)
            ->where('manufacture_task_id', $lastTask->manufacture_task_id)
            ->value('units_per_artefact') ?: 1;

        return (float) $lastTask->quantity_made / $unitsPerArtefact;
    }
}
