<?php

namespace App\Actions\UI\Incoming;

use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIncomingHubPalletDeliveryWidget
{
    use AsObject;

    public function handle(Warehouse $warehouse): array
    {
        $stats = $warehouse->stats;

        $routeParams = [
            'organisation' => $warehouse->organisation->slug,
            'warehouse'    => $warehouse->slug,
        ];

        $stateConfig = [
            PalletDeliveryStateEnum::SUBMITTED->value  => ['icon' => ['fal', 'fa-paper-plane'],    'label' => __('Submitted')],
            PalletDeliveryStateEnum::RECEIVED->value   => ['icon' => ['fal', 'fa-truck-loading'],  'label' => __('Received')],
            PalletDeliveryStateEnum::BOOKING_IN->value => ['icon' => ['fal', 'fa-clipboard-list'], 'label' => __('Booking In')],
            PalletDeliveryStateEnum::BOOKED_IN->value  => ['icon' => ['fal', 'fa-pallet-alt'],     'label' => __('Booked In')],
        ];

        $metrics    = [];
        $dataGlobal = [];
        $totals     = [];
        $total      = 0;

        foreach ($stateConfig as $stateValue => $config) {
            $count = $stats->{'number_pallet_deliveries_state_'.$stateValue} ?? 0;

            $metrics[] = [
                'key'     => $stateValue,
                'label'   => $config['label'],
                'type'    => 'stat',
                'icon'    => $config['icon'],
                'tooltip' => $config['label'],
            ];

            $dataGlobal[$stateValue] = [
                'value'        => $count,
                'route_target' => [
                    'name'       => 'grp.org.warehouses.show.incoming.pallet_deliveries.index',
                    'parameters' => $routeParams,
                ],
            ];

            $totals[$stateValue] = ['value' => $count];
            $total              += $count;
        }

        return [
            'metrics'    => $metrics,
            'data'       => ['_global' => $dataGlobal],
            'row_totals' => [
                '_global' => [
                    'value'        => $total,
                    'route_target' => [
                        'name'       => 'grp.org.warehouses.show.incoming.pallet_deliveries.index',
                        'parameters' => $routeParams,
                    ],
                ],
            ],
            'totals'      => $totals,
            'grand_total' => [
                'value'   => $total,
                'icon'    => ['fal', 'fa-truck-couch'],
                'tooltip' => __('Total Fulfilment Deliveries'),
                'route_target' => [
                    'name'       => 'grp.org.warehouses.show.incoming.pallet_deliveries.index',
                    'parameters' => $routeParams,
                ],
            ],
        ];
    }
}
