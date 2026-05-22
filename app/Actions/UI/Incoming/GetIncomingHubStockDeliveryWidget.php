<?php

namespace App\Actions\UI\Incoming;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIncomingHubStockDeliveryWidget
{
    use AsObject;

    public function handle(Warehouse $warehouse): array
    {
        $organisation = $warehouse->organisation;
        $stats        = $organisation->procurementStats;

        $routeParams = [
            'organisation' => $organisation->slug,
            'warehouse'    => $warehouse->slug,
        ];

        $stateConfig = [
            StockDeliveryStateEnum::RECEIVED->value => ['icon' => ['fal', 'fa-truck-loading'],   'label' => __('Received')],
            StockDeliveryStateEnum::CHECKED->value  => ['icon' => ['fal', 'fa-clipboard-check'], 'label' => __('Checked')],
            StockDeliveryStateEnum::PLACED->value   => ['icon' => ['fal', 'fa-pallet-alt'],      'label' => __('Placed')],
        ];

        $metrics    = [];
        $dataGlobal = [];
        $totals     = [];
        $total      = 0;

        foreach ($stateConfig as $stateValue => $config) {
            $count = $stats->{'number_stock_deliveries_state_'.$stateValue} ?? 0;

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
                    'name'       => 'grp.org.warehouses.show.incoming.stock_deliveries.index',
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
                        'name'       => 'grp.org.warehouses.show.incoming.stock_deliveries.index',
                        'parameters' => $routeParams,
                    ],
                ],
            ],
            'totals'      => $totals,
            'grand_total' => [
                'value'   => $total,
                'icon'    => ['fal', 'fa-truck-container'],
                'tooltip' => __('Total Stock Deliveries'),
                'route_target' => [
                    'name'       => 'grp.org.warehouses.show.incoming.stock_deliveries.index',
                    'parameters' => $routeParams,
                ],
            ],
        ];
    }
}
