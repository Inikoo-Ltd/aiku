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
                'icon'  => ['fal', 'fa-clock'],
            ],
            [
                'key'   => 'warehouse',
                'label' => __('Warehouse'),
                'type'  => 'group',
                'items' => [
                    [
                        'key'   => 'handling',
                        'label' => __('Picking'),
                        'icon'  => ['fal', 'fa-list'],
                    ],
                    [
                        'key'   => 'packed',
                        'label' => __('Packed'),
                        'icon'  => ['fal', 'fa-box'],
                    ],
                    [
                        'key'   => 'packing',
                        'label' => __('Packing'),
                        'icon'  => ['fal', 'fa-box-open'],
                    ],
                ],
            ],
            [
                'key'   => 'finalised',
                'label' => __('Finalised'),
                'type'  => 'stat',
                'icon'  => ['fal', 'fa-check-circle'],
            ],
        ];

        $allCaseKeysForTotals = ['todo', 'handling', 'packed', 'packing', 'finalised'];

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
            $rowKey                = $widget['slug'] ?? str($widget['label'])->slug()->toString();
            $rowTotals[$rowKey] = ['value' => $widget['total'] ?? 0];
        }

        $totals = [];
        foreach ($allCaseKeysForTotals as $caseKey) {
            $totals[$caseKey] = ['value' => $widgets->sum($caseKey)];
        }

        $grandTotal = $widgets->sum('total');

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
            ],
        ];
    }

    private function getMetricLabel(string $key): string
    {
        return match ($key) {
            'todo'             => __('To do'),
            'queued'           => __('Queued'),
            'handling'         => __('Picking'),
            'handling_blocked' => __('Waiting'),
            'picked'           => __('Picked'),
            'packing'          => __('Packing'),
            'packed'           => __('Packed'),
            'finalised'        => __('Finalised'),
            default            => ucfirst($key),
        };
    }

    private function getMetricIcon(string $key): array
    {
        return match ($key) {
            'todo'             => ['fal', 'fa-clock'],
            'queued'           => ['fal', 'fa-hourglass-start'],
            'handling'         => ['fal', 'fa-list'],
            'handling_blocked' => ['fal', 'fa-allergies'],
            'picked'           => ['fal', 'fa-check'],
            'packing'          => ['fal', 'fa-box-open'],
            'packed'           => ['fal', 'fa-box'],
            'finalised'        => ['fal', 'fa-check-circle'],
            default            => ['fal', 'fa-circle'],
        };
    }
}
