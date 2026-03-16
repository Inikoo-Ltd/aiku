<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:44:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

class DashboardDispatchHubDashboardResource extends JsonResource
{
    public function toArray($request): array
    {
        $widgets = collect($this->resource);

        $dimensionItems = $widgets->map(fn ($widget) => [
            'key'   => $widget['slug'] ?? str($widget['label'])->slug()->toString(),
            'label' => $widget['label'],
        ])->values()->toArray();

        $metrics = [
            [
                'key'   => 'todo',
                'label' => __('To do'),
                'type'  => 'stat',
                'icon'  => ['fal', 'fa-chair'],
                'tooltip' => 'To do'
            ],
            [
                'key'   => 'warehouse',
                'label' => __('Warehouse'),
                'type'  => 'group',
                'items' => [
                    [
                        'key'   => 'handling',
                        'label' => __('Picking'),
                        'icon'  => ['fal', 'fa-hand-paper'],
                        'tooltip' => 'Picking'
                    ],
                    [
                        'key'   => 'handling_blocked',
                        'label' => __('Waiting'),
                        'icon'  => ['fal', 'fa-snooze'],
                        'tooltip' => 'Waiting'
                    ],
                    [
                        'key'   => 'picked',
                        'label' => __('Picked'),
                        'icon'  => ['fal', 'fa-check'],
                        'tooltip' => 'Picked'
                    ],
                    [
                        'key'   => 'packing',
                        'label' => __('Packing'),
                        'icon'  => ['fal', 'fa-box-open'],
                        'tooltip' => 'Packing'
                    ],
                    [
                        'key'   => 'packed',
                        'label' => __('Packed'),
                        'icon'  => ['fal', 'fa-box'],
                        'tooltip' => 'Packed'
                    ],
                ],
            ],
            [
                'key'   => 'finalised',
                'label' => __('Finalised'),
                'type'  => 'stat',
                'icon'  => ['fal', 'fa-box-check'],
                'tooltip' => 'Finalised'
            ],
        ];

        $allCaseKeysForTotals = ['todo', 'handling', 'handling_blocked', 'picked', 'packing', 'packed', 'finalised'];

        $data = [];
        foreach ($widgets as $widget) {
            $rowKey = $widget['slug'] ?? str($widget['label'])->slug()->toString();
            $data[$rowKey] = [];

            foreach ($allCaseKeysForTotals as $caseKey) {
                $caseData = $widget['cases'][$caseKey] ?? null;
                $entry = [
                    'value' => $widget[$caseKey] ?? null,
                ];

                if ($caseData && isset($caseData['route'])) {
                    $entry['route_target'] = [
                        'name'       => $caseData['route']['name'],
                        'parameters' => $caseData['route']['parameters'] ?? [],
                    ];
                }

                $data[$rowKey][$caseKey] = $entry;
            }
        }

        $rowTotals = [];
        foreach ($widgets as $widget) {
            $rowKey            = $widget['slug'] ?? str($widget['label'])->slug()->toString();
            $rowTotals[$rowKey] = ['value' => $widget['total'] ?? 0];

            if (isset($widget['total_route'])) {
                $rowTotals[$rowKey]['route_target'] = [
                    'name'       => $widget['total_route']['name'],
                    'parameters' => $widget['total_route']['parameters'] ?? [],
                ];
            }
        }

        $deliveryNotesWidget = $widgets->first(function ($widget) {
            $route = $widget['cases']['todo']['route']['name'] ?? '';

            return str_contains($route, 'delivery-notes');
        });

        $totals = [];
        foreach ($allCaseKeysForTotals as $caseKey) {
            $totals[$caseKey] = ['value' => $widgets->sum($caseKey)];

            if ($deliveryNotesWidget && isset($deliveryNotesWidget['cases'][$caseKey]['route'])) {
                $caseRoute                      = $deliveryNotesWidget['cases'][$caseKey]['route'];
                $totals[$caseKey]['route_target'] = [
                    'name'       => str_replace('.shop', '', $caseRoute['name']),
                    'parameters' => array_slice($caseRoute['parameters'], 0, -1),
                ];
            }
        }

        $grandTotal = $widgets->sum('total');

        $routeParams = $totals['todo']['route_target']['parameters'] ?? [];
        return [
            'dimension'   => [
                'key'   => 'channel',
                'label' => __('Channel'),
                'items' => $dimensionItems,
            ],
            'metrics'     => $metrics,
            'data'        => $data,
            'row_totals'  => $rowTotals,
            'totals'      => $totals,
            'grand_total' => [
                'value' => $grandTotal,
                'icon'  => ['fal', 'fa-chart-line'],
                'tooltip' => 'Total',
                'route_target' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.in_warehouse.delivery-notes',
                        'parameters' => $routeParams,
                ],
            ],
        ];
    }
}
