<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Aug 2024 12:57:59 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Incoming;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowIncomingHub extends OrgAction
{
    public function handle(Warehouse $warehouse): Warehouse
    {
        return $warehouse;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("incoming.{$this->warehouse->id}.view");
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Warehouse
    {
        $this->initialisationFromWarehouse($warehouse, []);

        return $this->handle($warehouse);
    }

    public function htmlResponse(Warehouse $warehouse, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Incoming/IncomingHub',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'    => 'incoming',
                'pageHead' => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-arrow-to-bottom'],
                        'title' => __('Incoming')
                    ],
                    'title' => __('Incoming Hub'),
                ],
                'dashboard' => $this->getIncomingDashboard($warehouse),
            ]
        );
    }

    private function getIncomingDashboard(Warehouse $warehouse): array
    {
        $parts = [
            [
                'key'    => 'stock_deliveries',
                'label'  => __('Stock Deliveries'),
                'widget' => GetIncomingHubStockDeliveryWidget::run($warehouse),
            ],
            [
                'key'    => 'pallet_deliveries',
                'label'  => __('Fulfilment Deliveries'),
                'widget' => GetIncomingHubPalletDeliveryWidget::run($warehouse),
            ],
            [
                'key'    => 'return_delivery_notes',
                'label'  => __('Returns'),
                'widget' => GetIncomingHubReturnDeliveryNoteWidget::run($warehouse),
            ],
        ];

        $metrics       = [];
        $metricKeys    = [];
        $data          = [];
        $rowTotals     = [];
        $columnTotals  = [];
        $grandTotal    = 0;

        foreach ($parts as $part) {
            foreach ($part['widget']['metrics'] as $metric) {
                if (!in_array($metric['key'], $metricKeys, true)) {
                    $metricKeys[] = $metric['key'];
                    $metrics[]    = $metric;
                }
            }
        }

        $metricOrder = [
            'received',
            'checked',
            'booking_in',
            'booked_in',
            'placed',
        ];

        usort($metrics, function ($a, $b) use ($metricOrder) {
            $posA = array_search($a['key'], $metricOrder, true);
            $posB = array_search($b['key'], $metricOrder, true);
            $posA = $posA === false ? PHP_INT_MAX : $posA;
            $posB = $posB === false ? PHP_INT_MAX : $posB;

            return $posA <=> $posB;
        });

        $metricKeys = array_map(fn ($metric) => $metric['key'], $metrics);

        foreach ($parts as $part) {
            $rowKey          = $part['key'];
            $widget          = $part['widget'];
            $globalData      = $widget['data']['_global'] ?? [];
            $data[$rowKey]   = [];

            foreach ($metricKeys as $metricKey) {
                if (array_key_exists($metricKey, $globalData)) {
                    $entry = $globalData[$metricKey];
                    $data[$rowKey][$metricKey] = $entry;

                    $columnTotals[$metricKey]['value'] = ($columnTotals[$metricKey]['value'] ?? 0) + ($entry['value'] ?? 0);

                    if (isset($entry['prefix'])) {
                        $columnTotals[$metricKey]['prefix']['value']        = ($columnTotals[$metricKey]['prefix']['value'] ?? 0) + ($entry['prefix']['value'] ?? 0);
                        $columnTotals[$metricKey]['prefix']['tooltip']      = $entry['prefix']['tooltip'] ?? null;
                        $columnTotals[$metricKey]['prefix']['route_target'] = $entry['prefix']['route_target'] ?? null;
                    }

                    if (isset($entry['suffix'])) {
                        $columnTotals[$metricKey]['suffix']['value']        = ($columnTotals[$metricKey]['suffix']['value'] ?? 0) + ($entry['suffix']['value'] ?? 0);
                        $columnTotals[$metricKey]['suffix']['tooltip']      = $entry['suffix']['tooltip'] ?? null;
                        $columnTotals[$metricKey]['suffix']['route_target'] = $entry['suffix']['route_target'] ?? null;
                    }
                } else {
                    $data[$rowKey][$metricKey] = ['value' => null];
                }
            }

            $rowTotal           = $widget['row_totals']['_global']['value'] ?? 0;
            $rowAffixTotal      = 0;
            foreach ($globalData as $entry) {
                $rowAffixTotal += ($entry['prefix']['value'] ?? 0) + ($entry['suffix']['value'] ?? 0);
            }
            $rowTotals[$rowKey] = ['value' => $rowTotal + $rowAffixTotal];
            $grandTotal        += $rowTotals[$rowKey]['value'];
        }

        return [
            'dimension' => [
                'key'   => 'type',
                'label' => __('Type'),
                'items' => array_map(fn ($part) => ['key' => $part['key'], 'label' => $part['label']], $parts),
            ],
            'metrics'     => $metrics,
            'data'        => $data,
            'row_totals'  => $rowTotals,
            'totals'      => $columnTotals,
            'grand_total' => [
                'value'   => $grandTotal,
                'icon'    => ['fal', 'fa-arrow-to-bottom'],
                'tooltip' => __('Total Incoming'),
            ],
        ];
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.incoming.backlog',
                            'parameters' => $routeParameters
                        ],
                        'icon'  => ['fal', 'fa-arrow-to-bottom'],
                        'label' => __('Goods in'),
                    ]
                ]
            ]
        );
    }
}
