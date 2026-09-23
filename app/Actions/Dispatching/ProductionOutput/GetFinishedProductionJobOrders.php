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
use App\Models\Production\JobOrderItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetFinishedProductionJobOrders
{
    use AsAction;

    /**
     * One row per destination, not per job order: the warehouse walks to a bay once and carries
     * everything every artisan has finished for it. Quantities come from the to-produce lines
     * behind each job order, filled whole and biggest first.
     *
     * A job carried to another day is one job, not two: every part of the chain is grouped under
     * the job it came from, so goods in read one making with one full amount, and a chain still
     * being worked says what is made against what the whole job asks for.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(Warehouse $warehouse): array
    {
        $jobOrders = JobOrder::where('organisation_id', $warehouse->organisation_id)
            ->where('state', JobOrderStateEnum::CONFIRMED)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('job_order_item_tasks')->whereColumn('job_order_item_tasks.job_order_id', 'job_orders.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('job_order_item_tasks')->whereColumn('job_order_item_tasks.job_order_id', 'job_orders.id')->where('job_order_item_tasks.state', '!=', 'done'))
            ->with(['employee', 'jobOrderItems.artefact.orgStock.locations','jobOrderItems.tasks'])
            ->orderBy('confirmed_at')
            ->get();

        $partnersByLocation = OrgPartner::where('organisation_id', $warehouse->organisation_id)
            ->whereNotNull('goods_out_location_id')
            ->with('partner')
            ->get()
            ->keyBy('goods_out_location_id');

        $locations = Location::whereIn('id', $partnersByLocation->keys())->pluck('code', 'id');

        $trips = [];
        $roots = [];

        foreach ($jobOrders as $jobOrder) {
            $root           = $this->root($jobOrder);
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
                $trips[$key]['job_order_ids'][$jobOrder->id] = $jobOrder->id;

                $packedIn       = max(1, (int) $item->artefact->orgStock?->packed_in);
                $stockLocations = $locationId ? [] : ($item->artefact->orgStock?->locations->sortBy('pivot.picking_priority')
                    ->map(fn (Location $location) => ['code' => $location->code, 'quantity' => round((float) $location->pivot->quantity, 3)])->values()->all() ?? []);

                $group = $trips[$key]['jobs'][$root][$item->artefact_id] ?? [];
                $trips[$key]['jobs'][$root][$item->artefact_id] = [
                    'root'           => $root,
                    'artefact_id'    => $item->artefact_id,
                    'reference'      => $this->baseReference($jobOrder),
                    'artisan'        => $jobOrder->employee?->contact_name,
                    'item_id'        => $group['item_id'] ?? $item->id,
                    'item_ids'       => array_values(array_unique([...($group['item_ids'] ?? []), $item->id])),
                    'job_order_ids'  => array_values(array_unique([...($group['job_order_ids'] ?? []), $jobOrder->id])),
                    'location_code'  => $stockLocations[0]['code'] ?? null,
                    'locations'      => $stockLocations,
                    'code'           => $item->artefact->code,
                    'name'           => $item->artefact->name,
                    'quantity'       => round(($group['quantity'] ?? 0) + $quantity / $packedIn, 3),
                ];

                $roots[$root] = $root;
            }
        }

        $chainTotals = $this->chainTotals($roots);

        return collect($trips)->map(fn (array $trip) => [
            'destination'   => $trip['destination'],
            'job_order_ids' => array_values($trip['job_order_ids']),
            'jobs'          => collect($trip['jobs'])->map(fn (array $byArtefact) => [
                'reference' => reset($byArtefact)['reference'],
                'artisan'   => reset($byArtefact)['artisan'],
                'items'     => collect($byArtefact)->map(function (array $group) use ($chainTotals) {
                    $totals = $chainTotals[$group['root']][$group['artefact_id']] ?? ['made' => $group['quantity'], 'total' => $group['quantity']];

                    return [
                        ...$group,
                        'quantity_made'  => $totals['made'],
                        'quantity_total' => $totals['total'],
                        'in_progress'    => $totals['total'] > $totals['made'] + 0.0001,
                    ];
                })->values()->all(),
            ])->values()->all(),
        ])->values()->all();
    }

    private function root(JobOrder $jobOrder): int
    {
        return (int) Arr::get($jobOrder->data, 'carried_from', $jobOrder->id);
    }

    private function baseReference(JobOrder $jobOrder): string
    {
        return (string) preg_replace('/[a-z]+$/', '', (string) $jobOrder->reference);
    }

    /**
     * What the whole job asks for against what it has made so far, counted over every part of a
     * carried chain, so a job still being worked reads 23 of 40 instead of a finished 23.
     * ponytail: totals are per job and artefact, not per destination; a chain split over two bays
     * shows the same job total on both rows, split it per destination if that ever confuses.
     *
     * @param  array<int, int>  $roots
     * @return array<int, array<int, array{made: float, total: float}>>
     */
    private function chainTotals(array $roots): array
    {
        if (!$roots) {
            return [];
        }

        $totals = [];

        foreach ($this->chainMembers($roots) as $jobOrder) {
            $root     = $this->root($jobOrder);
            $ordered  = $jobOrder->jobOrderItems->mapWithKeys(fn (JobOrderItem $item) => [$item->id => (float) $item->quantity])->all();

            foreach (['made' => [], 'total' => $ordered] as $side => $madeByItem) {
                foreach (GetJobOrderDestinationAllocation::run($jobOrder, $madeByItem) as $allocation) {
                    $item     = $allocation['item'];
                    $packedIn = max(1, (int) $item->artefact->orgStock?->packed_in);

                    $totals[$root][$item->artefact_id][$side] = round(($totals[$root][$item->artefact_id][$side] ?? 0) + $allocation['quantity'] / $packedIn, 3);
                }
            }
        }

        return $totals;
    }

    /**
     * A part of the chain already walked into stock still counts towards the job: what the whole
     * job asks for does not shrink because the first day is on the shelf.
     *
     * @param  array<int, int>  $roots
     * @return Collection<int, JobOrder>
     */
    private function chainMembers(array $roots): Collection
    {
        return JobOrder::whereIn('state', [
            JobOrderStateEnum::CONFIRMED,
            JobOrderStateEnum::RECEIVED,
            JobOrderStateEnum::NOT_RECEIVED,
            JobOrderStateEnum::BOOKING_IN,
            JobOrderStateEnum::BOOKED_IN,
        ])
            ->where(function ($query) use ($roots) {
                $query->whereIn('id', $roots);
                foreach ($roots as $root) {
                    $query->orWhere('data->carried_from', $root);
                }
            })
            ->with(['jobOrderItems.artefact.orgStock', 'jobOrderItems.tasks'])
            ->get();
    }
}
