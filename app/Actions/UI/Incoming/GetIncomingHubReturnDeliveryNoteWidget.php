<?php

namespace App\Actions\UI\Incoming;

use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIncomingHubReturnDeliveryNoteWidget
{
    use AsObject;

    public function handle(Warehouse $warehouse): array
    {
        $stats = $warehouse->stats;

        $routeParams = [
            'organisation' => $warehouse->organisation->slug,
            'warehouse'    => $warehouse->slug,
        ];

        $labels = ReturnDeliveryNoteStateEnum::labels();

        $stateConfig = [
            ReturnDeliveryNoteStateEnum::RECEIVED->value  => [
                'icon'  => ['fal', 'fa-chair'],
                'route' => 'grp.org.warehouses.show.incoming.return_delivery_notes.state.received',
            ],
            ReturnDeliveryNoteStateEnum::RETURNING->value => [
                'icon'  => ['fal', 'fa-hand-paper'],
                'route' => 'grp.org.warehouses.show.incoming.return_delivery_notes.state.returning',
            ],
            ReturnDeliveryNoteStateEnum::RETURNED->value  => [
                'icon'  => ['fal', 'fa-check'],
                'route' => 'grp.org.warehouses.show.incoming.return_delivery_notes.state.returned',
            ],
            ReturnDeliveryNoteStateEnum::DONE->value      => [
                'icon'  => ['fal', 'fa-check-double'],
                'route' => 'grp.org.warehouses.show.incoming.return_delivery_notes.state.processed',
            ],
        ];

        $metrics    = [];
        $dataGlobal = [];
        $totals     = [];
        $total      = 0;

        foreach ($stateConfig as $stateValue => $config) {
            $count = $stats->{'number_return_delivery_notes_state_'.$stateValue} ?? 0;
            $label = $labels[$stateValue] ?? ucfirst($stateValue);

            $metrics[] = [
                'key'     => $stateValue,
                'label'   => $label,
                'type'    => 'stat',
                'icon'    => $config['icon'],
                'tooltip' => $label,
            ];

            $dataGlobal[$stateValue] = [
                'value'        => $count,
                'route_target' => [
                    'name'       => $config['route'],
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
                        'name'       => 'grp.org.warehouses.show.incoming.return_delivery_notes.index',
                        'parameters' => $routeParams,
                    ],
                ],
            ],
            'totals'      => $totals,
            'grand_total' => [
                'value'   => $total,
                'icon'    => ['fal', 'fa-exchange'],
                'tooltip' => __('Total Return Delivery Notes'),
                'route_target' => [
                    'name'       => 'grp.org.warehouses.show.incoming.return_delivery_notes.index',
                    'parameters' => $routeParams,
                ],
            ],
        ];
    }
}
