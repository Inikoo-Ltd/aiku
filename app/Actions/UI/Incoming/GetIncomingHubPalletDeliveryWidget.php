<?php

namespace App\Actions\UI\Incoming;

use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\Fulfilment\PalletDelivery;
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
            'arriving'                                 => ['icon' => ['fal', 'fa-truck'],           'label' => __('Arriving')],
            PalletDeliveryStateEnum::RECEIVED->value   => ['icon' => ['fal', 'fa-chair'],           'label' => __('Received')],
            PalletDeliveryStateEnum::BOOKING_IN->value => ['icon' => ['fal', 'fa-clipboard-list'],  'label' => __('Booking In')],
            PalletDeliveryStateEnum::BOOKED_IN->value  => ['icon' => ['fal', 'fa-pallet-alt'],      'label' => __('Booked In'), 'tooltip' => __('Booked in, last 7 days')],
        ];

        $metrics    = [];
        $dataGlobal = [];
        $totals     = [];
        $total      = 0;

        foreach ($stateConfig as $stateValue => $config) {
            $count = match ($stateValue) {
                'arriving' => ($stats->{'number_pallet_deliveries_state_'.PalletDeliveryStateEnum::SUBMITTED->value} ?? 0)
                    + ($stats->{'number_pallet_deliveries_state_'.PalletDeliveryStateEnum::CONFIRMED->value} ?? 0),
                PalletDeliveryStateEnum::BOOKED_IN->value => PalletDelivery::where('warehouse_id', $warehouse->id)
                    ->where('state', PalletDeliveryStateEnum::BOOKED_IN)
                    ->where('booked_in_at', '>=', now()->subDays(6)->startOfDay())
                    ->count(),
                default => $stats->{'number_pallet_deliveries_state_'.$stateValue} ?? 0,
            };

            $metrics[] = [
                'key'     => $stateValue,
                'label'   => $config['label'],
                'type'    => 'stat',
                'icon'    => $config['icon'],
                'tooltip' => $config['tooltip'] ?? $config['label'],
            ];

            $entry = [
                'value'        => $count,
                'route_target' => [
                    'name'       => 'grp.org.warehouses.show.incoming.pallet_deliveries.index',
                    'parameters' => [
                        ...$routeParams,
                        'deliveries_elements[state]' => match ($stateValue) {
                            'arriving' => PalletDeliveryStateEnum::SUBMITTED->value.','.PalletDeliveryStateEnum::CONFIRMED->value,
                            default    => $stateValue,
                        },
                        ...($stateValue === PalletDeliveryStateEnum::BOOKED_IN->value
                            ? ['deliveries_between[booked_in_at]' => now()->subDays(6)->format('Ymd').'-'.now()->format('Ymd')]
                            : []),
                    ],
                ],
            ];

            $dataGlobal[$stateValue] = $entry;

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
