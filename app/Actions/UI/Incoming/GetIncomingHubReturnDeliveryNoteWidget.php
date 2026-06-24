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
            'received' => [
                'state' => ReturnDeliveryNoteStateEnum::RECEIVED->value,
                'icon'  => ['fal', 'fa-chair'],
                'label' => __('To do'),
                'route' => 'grp.org.warehouses.show.incoming.return_delivery_notes.state.received',
            ],
            'booking_in' => [
                'state' => ReturnDeliveryNoteStateEnum::RETURNING->value,
                'icon'  => ['fal', 'fa-clipboard-list'],
                'label' => __('Booking In'),
                'route' => 'grp.org.warehouses.show.incoming.return_delivery_notes.state.returning',
            ],
        ];

        $metrics    = [];
        $dataGlobal = [];
        $totals     = [];
        $total      = 0;

        foreach ($stateConfig as $key => $config) {
            $stateValue = $config['state'];
            $count      = $stats->{'number_return_delivery_notes_state_'.$stateValue} ?? 0;
            $label      = $config['label'] ?? $labels[$stateValue] ?? ucfirst($stateValue);

            $metrics[] = [
                'key'     => $key,
                'label'   => $label,
                'type'    => 'stat',
                'icon'    => $config['icon'],
                'tooltip' => $label,
            ];

            $dataGlobal[$key] = [
                'value'        => $count,
                'route_target' => [
                    'name'       => $config['route'],
                    'parameters' => $routeParams,
                ],
            ];

            $totals[$key] = ['value' => $count];
            $total      += $count;
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
