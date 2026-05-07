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
        $waitingItemsStillPicking    = $this->resource['waiting_items_still_picking'] ?? ['count' => 0, 'route' => null];
        $waitingItems                = $this->resource['waiting_items'] ?? ['count' => 0, 'route' => null];
        $waitingCrmItemsStillPicking = $this->resource['waiting_crm_items_still_picking'] ?? ['count' => 0, 'route' => null];
        $waitingCrmItems             = $this->resource['waiting_crm_items'] ?? ['count' => 0, 'route' => null];

        $widgets = collect($this->resource)->filter(fn ($widget, $key) => is_int($key))->values();

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

                if (is_array($caseData['route'] ?? null) && isset($caseData['route']['name'])) {
                    $entry['route_target'] = [
                        'name'       => $caseData['route']['name'],
                        'parameters' => $caseData['route']['parameters'] ?? [],
                    ];
                }

                if ($caseKey === 'handling' && ($widget['queued'] ?? 0) > 0) {
                    $queuedCaseData              = $widget['cases']['queued'] ?? null;
                    $entry['queued_prefix']       = ['value' => $widget['queued']];
                    if ($queuedCaseData && isset($queuedCaseData['route'])) {
                        $entry['queued_prefix']['route_target'] = [
                            'name'       => $queuedCaseData['route']['name'],
                            'parameters' => $queuedCaseData['route']['parameters'] ?? [],
                        ];
                    }
                }

                if ($caseKey === 'handling' && $widget['waiting_items_still_picking']['count'] > 0) {
                    $entry['warning'] = [
                        'route_target' => $widget['waiting_items_still_picking']['route'],
                        'tooltip' => __('Waiting items in delivery notes still picking'),
                        'value' => $widget['waiting_items_still_picking']['count'],
                    ];
                }

                if ($caseKey === 'handling_blocked' && $widget['waiting_items']['count'] > 0) {
                    $entry['warning'] = [
                        'route_target' => $widget['waiting_items']['route'],
                        'tooltip' => __('Waiting items'),
                        'value' => $widget['waiting_items']['count'],
                    ];
                }

                if ($caseKey === 'handling' && ($widget['waiting_crm_items_still_picking']['count'] ?? 0) > 0) {
                    $entry['crm_warning'] = [
                        'route_target' => $widget['waiting_crm_items_still_picking']['route'],
                        'tooltip' => $widget['waiting_crm_items_still_picking']['tooltip'] ?? __('CRM waiting items in delivery notes still picking'),
                        'value' => $widget['waiting_crm_items_still_picking']['count'],
                    ];
                }

                if ($caseKey === 'handling_blocked' && ($widget['waiting_crm_items']['count'] ?? 0) > 0) {
                    $entry['crm_warning'] = [
                        'route_target' => $widget['waiting_crm_items']['route'],
                        'tooltip' => __('CRM waiting items'),
                        'value' => $widget['waiting_crm_items']['count'],
                    ];
                }

                $data[$rowKey][$caseKey] = $entry;
            }
        }

        $rowTotals = [];
        foreach ($widgets as $widget) {
            $rowKey            = $widget['slug'] ?? str($widget['label'])->slug()->toString();
            $rowTotals[$rowKey] = ['value' => $widget['total'] ?? 0];

            if (is_array($widget['total_route'] ?? null) && isset($widget['total_route']['name'])) {
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

            $caseRoute = $deliveryNotesWidget['cases'][$caseKey]['route'] ?? null;
            if (is_array($caseRoute) && isset($caseRoute['name'])) {
                $totals[$caseKey]['route_target'] = [
                    'name'       => str_replace('.shop', '', $caseRoute['name']),
                    'parameters' => array_slice($caseRoute['parameters'] ?? [], 0, -1),
                ];
            }

            if ($caseKey === 'handling' && $waitingItemsStillPicking['count'] > 0) {
                $totals[$caseKey]['warning'] = [
                    'route_target' => $waitingItemsStillPicking['route'],
                    'tooltip'      => __('Waiting items in delivery notes still picking'),
                    'value'        => $waitingItemsStillPicking['count'],
                ];
            }

            if ($caseKey === 'handling_blocked' && $waitingItems['count'] > 0) {
                $totals[$caseKey]['warning'] = [
                    'route_target' => $waitingItems['route'],
                    'tooltip'      => __('Waiting items'),
                    'value'        => $waitingItems['count'],
                ];
            }

            if ($caseKey === 'handling' && $waitingCrmItemsStillPicking['count'] > 0) {
                $totals[$caseKey]['crm_warning'] = [
                    'route_target' => $waitingCrmItemsStillPicking['route'],
                    'tooltip'      => $waitingCrmItemsStillPicking['tooltip'] ?? __('CRM waiting items in delivery notes still picking'),
                    'value'        => $waitingCrmItemsStillPicking['count'],
                ];
            }

            if ($caseKey === 'handling_blocked' && $waitingCrmItems['count'] > 0) {
                $totals[$caseKey]['crm_warning'] = [
                    'route_target' => $waitingCrmItems['route'],
                    'tooltip'      => __('CRM waiting items'),
                    'value'        => $waitingCrmItems['count'],
                ];
            }
        }

        $totalQueued = $widgets->sum('queued');
        if ($totalQueued > 0 && $deliveryNotesWidget && isset($deliveryNotesWidget['cases']['queued']['route'])) {
            $queuedRoute                            = $deliveryNotesWidget['cases']['queued']['route'];
            $totals['handling']['queued_prefix']    = [
                'value'        => $totalQueued,
                'route_target' => [
                    'name'       => str_replace('.shop', '', $queuedRoute['name']),
                    'parameters' => array_slice($queuedRoute['parameters'], 0, -1),
                ],
            ];
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
