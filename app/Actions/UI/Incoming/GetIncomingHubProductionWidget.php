<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 12:00:00 Central European Standard Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Incoming;

use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Inventory\Warehouse;
use App\Models\Production\JobOrder;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIncomingHubProductionWidget
{
    use AsObject;

    /**
     * Job orders still being made arrive, counted per state; a confirmed one already
     * waiting to be put away counts there instead. Finished work waiting to be put away reads as received: what goes to stock locations,
     * plus what goes to partner bays. Booked in is what was put away in the last 7 days.
     *
     * Finished lines are counted as the rows each filtered From production page shows; a line
     * split between stock and a partner bay is on both, so the total reads as the sum. The hub adds the partner suffix
     * to the row total itself, so it is left out of row_totals here.
     * ponytail: counts are organisation-wide, so the job orders list is linked only when the
     * organisation has one production; link per production if one ever runs two.
     *
     * @param  array<int, array<string, mixed>>  $trips  as returned by GetFinishedProductionJobOrders
     */
    public function handle(Warehouse $warehouse, array $trips, bool $canViewJobOrders): array
    {
        $itemIds = ['stock' => [], 'partner' => []];
        foreach ($trips as $trip) {
            foreach ($trip['jobs'] as $job) {
                foreach ($job['items'] as $item) {
                    $itemIds[$trip['destination']['type']][$item['item_id']] = true;
                }
            }
        }
        $waiting = ['stock' => count($itemIds['stock']), 'partner' => count($itemIds['partner'])];
        $waitingTotal = $waiting['stock'] + $waiting['partner'];

        $bookedIn = JobOrder::where('organisation_id', $warehouse->organisation_id)
            ->where('state', JobOrderStateEnum::RECEIVED)
            ->where('received_at', '>=', now()->subDays(6)->startOfDay())
            ->count();

        $waitingJobOrderIds = collect($trips)->flatMap(fn (array $trip) => $trip['job_order_ids'])->unique()->all();
        $beingMade          = JobOrder::where('organisation_id', $warehouse->organisation_id)
            ->whereIn('state', JobOrderStateEnum::open())
            ->where(fn ($query) => $query->where('state', '!=', JobOrderStateEnum::CONFIRMED)->orWhereNotIn('id', $waitingJobOrderIds))
            ->selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state');

        $productions = $warehouse->organisation->productions()->limit(2)->get();
        $production  = $canViewJobOrders && $productions->count() === 1 ? $productions->first() : null;
        $stateIcons = JobOrderStateEnum::stateIcon();
        $arriving   = array_map(fn (JobOrderStateEnum $state) => [
            'value'   => (int) ($beingMade[$state->value] ?? 0),
            'icon'    => $stateIcons[$state->value]['icon'],
            'class'   => $stateIcons[$state->value]['class'],
            'tooltip' => __('Job orders :state, still being made', ['state' => mb_strtolower(JobOrderStateEnum::labels()[$state->value])]),
            'route_target' => $production ? [
                'name'       => 'grp.org.productions.show.operations.job-orders.index',
                'parameters' => [
                    'organisation'  => $warehouse->organisation->slug,
                    'production'    => $production->slug,
                    'filter[state]' => $state->value,
                ],
            ] : null,
        ], JobOrderStateEnum::open());
        $arrivingTotal = array_sum(array_column($arriving, 'value'));

        $route = fn (?string $destination = null) => [
            'name'       => 'grp.org.warehouses.show.incoming.production_output.index',
            'parameters' => array_filter([
                'organisation' => $warehouse->organisation->slug,
                'warehouse'    => $warehouse->slug,
                'destination'  => $destination,
            ]),
        ];

        return [
            'metrics' => [
                ['key' => 'arriving', 'label' => __('Arriving'), 'type' => 'stat', 'icon' => ['fal', 'fa-truck'], 'tooltip' => __('Arriving')],
                ['key' => 'received', 'label' => __('Received'), 'type' => 'stat', 'icon' => ['fal', 'fa-chair'], 'tooltip' => __('Received')],
                ['key' => 'booked_in', 'label' => __('Booked In'), 'type' => 'stat', 'icon' => ['fal', 'fa-pallet-alt'], 'tooltip' => __('Booked in, last 7 days')],
            ],
            'data' => [
                '_global' => [
                    'arriving' => [
                        'value'  => $arrivingTotal,
                        'series' => $arriving,
                    ],
                    'received' => [
                        'value'        => $waiting['stock'],
                        'tooltip'      => __('Finished, waiting to go to stock locations'),
                        'route_target' => $route('stock'),
                        'suffix'       => [
                            'value'        => $waiting['partner'],
                            'tooltip'      => __('Finished, waiting to go to partner bays'),
                            'route_target' => $route('partner'),
                        ],
                        'total'        => [
                            'value'        => $waitingTotal,
                            'tooltip'      => __('Finished, waiting to be put away'),
                            'route_target' => $route(),
                        ],
                    ],
                    'booked_in' => [
                        'value'        => $bookedIn,
                        'tooltip'      => __('Job orders put away, last 7 days'),
                        'route_target' => $production ? [
                            'name'       => 'grp.org.productions.show.operations.job-orders.index',
                            'parameters' => [
                                'organisation'          => $warehouse->organisation->slug,
                                'production'            => $production->slug,
                                'filter[state]'         => JobOrderStateEnum::RECEIVED->value,
                                'between[received_at]'  => now()->subDays(6)->format('Ymd').'-'.now()->format('Ymd'),
                            ],
                        ] : null,
                    ],
                ],
            ],
            'row_totals' => ['_global' => ['value' => $arrivingTotal + $waitingTotal - $waiting['partner'] + $bookedIn]],
        ];
    }
}
