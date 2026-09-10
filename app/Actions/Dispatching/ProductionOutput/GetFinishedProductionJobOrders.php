<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 18:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\ProductionOutput;

use App\Actions\Production\JobOrder\GetJobOrderDestinationAllocation;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgPartner;
use App\Models\Production\JobOrder;
use Lorisleiva\Actions\Concerns\AsAction;

class GetFinishedProductionJobOrders
{
    use AsAction;

    /**
     * One row per destination, not per job order: the warehouse walks to a bay once and carries
     * everything every artisan has finished for it. Quantities come from the to-produce lines
     * behind each job order, filled whole and biggest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(Warehouse $warehouse): array
    {
        $jobOrders = JobOrder::where('organisation_id', $warehouse->organisation_id)
            ->where('state', JobOrderStateEnum::CONFIRMED)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('job_order_item_tasks')->whereColumn('job_order_item_tasks.job_order_id', 'job_orders.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('job_order_item_tasks')->whereColumn('job_order_item_tasks.job_order_id', 'job_orders.id')->where('job_order_item_tasks.state', '!=', 'done'))
            ->with(['employee', 'jobOrderItems.artefact', 'jobOrderItems.tasks'])
            ->orderBy('confirmed_at')
            ->get();

        $partnersByLocation = OrgPartner::where('organisation_id', $warehouse->organisation_id)
            ->whereNotNull('goods_out_location_id')
            ->with('partner')
            ->get()
            ->keyBy('goods_out_location_id');

        $locations = Location::whereIn('id', $partnersByLocation->keys())->pluck('code', 'id');

        $trips = [];

        foreach ($jobOrders as $jobOrder) {
            $alreadyPutAway = [];

            foreach (GetJobOrderDestinationAllocation::run($jobOrder) as $allocation) {
                $item = $allocation['item'];

                $alreadyPutAway[$item->id] ??= (float) $item->quantity_received;
                $putAway                     = min($allocation['quantity'], $alreadyPutAway[$item->id]);
                $alreadyPutAway[$item->id]  -= $putAway;
                $quantity                    = $allocation['quantity'] - $putAway;

                if ($quantity <= 0) {
                    continue;
                }

                $locationId = $allocation['location_id'];
                $key        = $locationId ?? 'stock';

                $trips[$key]['destination'] ??= $locationId
                    ? ['type' => 'partner', 'label' => $partnersByLocation[$locationId]->partner->code, 'location_code' => $locations[$locationId]]
                    : ['type' => 'stock', 'label' => __('Stock'), 'location_code' => null];
                $trips[$key]['job_order_ids'][$jobOrder->id]                = $jobOrder->id;
                $trips[$key]['jobs'][$jobOrder->id]['reference']            = $jobOrder->reference;
                $trips[$key]['jobs'][$jobOrder->id]['artisan']              = $jobOrder->employee?->contact_name;
                $trips[$key]['jobs'][$jobOrder->id]['items'][$item->id]     = [
                    'code'     => $item->artefact->code,
                    'name'     => $item->artefact->name,
                    'quantity' => ($trips[$key]['jobs'][$jobOrder->id]['items'][$item->id]['quantity'] ?? 0) + $quantity,
                ];
            }
        }

        return collect($trips)->map(fn (array $trip) => [
            'destination'   => $trip['destination'],
            'job_order_ids' => array_values($trip['job_order_ids']),
            'jobs'          => collect($trip['jobs'])->map(fn (array $job) => [
                'reference' => $job['reference'],
                'artisan'   => $job['artisan'],
                'items'     => array_values($job['items']),
            ])->values()->all(),
        ])->values()->all();
    }
}
