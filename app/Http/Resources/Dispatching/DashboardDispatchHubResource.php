<?php

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

class DashboardDispatchHubResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = (array) $this->resource;

        $routeTargets = [];
        if (!empty($data['cases']) && is_array($data['cases'])) {
            foreach ($data['cases'] as $caseKey => $case) {
                $routeTargets[$caseKey] = isset($case['route']) ? [
                    'route_target' => [
                        'name'       => $case['route']['name'] ?? null,
                        'parameters' => $case['route']['parameters'] ?? [],
                    ],
                ] : [];
            }
        }

        $columns = [
            'label' => [
                'formatted_value' => $data['label'] ?? 'Unknown',
                'tooltip'         => $data['tooltip'] ?? '',
                'align'           => 'left',
            ],
            'todo' => [
                'raw_value'       => $data['todo'] ?? 0,
                'tooltip'         => '',
                'formatted_value' => $data['todo'] ?? '--',
                ...($routeTargets['todo'] ?? []),
            ],
            'queued' => [
                'raw_value'       => $data['queued'] ?? 0,
                'tooltip'         => '',
                'formatted_value' => $data['queued'] ?? '--',
                ...($routeTargets['queued'] ?? []),
            ],
            'handling' => [
                'raw_value'       => $data['handling'] ?? 0,
                'tooltip'         => '',
                'formatted_value' => $data['handling'] ?? '--',
                ...($routeTargets['handling'] ?? []),
            ],
            'handling_blocked' => [
                'raw_value'       => $data['handling_blocked'] ?? 0,
                'tooltip'         => '',
                'formatted_value' => $data['handling_blocked'] ?? '--',
                ...($routeTargets['handling_blocked'] ?? []),
            ],
            'picked' => [
                'raw_value'       => $data['picked'] ?? 0,
                'tooltip'         => '',
                'formatted_value' => $data['picked'] ?? '--',
                ...($routeTargets['picked'] ?? []),
            ],
            'packing' => [
                'raw_value'       => $data['packing'] ?? 0,
                'tooltip'         => '',
                'formatted_value' => $data['packing'] ?? '--',
                ...($routeTargets['packing'] ?? []),
            ],
            'packed' => [
                'raw_value'       => $data['packed'] ?? 0,
                'tooltip'         => '',
                'formatted_value' => $data['packed'] ?? '--',
                ...($routeTargets['packed'] ?? []),
            ],
            'finalised' => [
                'raw_value'       => $data['finalised'] ?? 0,
                'tooltip'         => '',
                'formatted_value' => $data['finalised'] ?? '--',
                ...($routeTargets['finalised'] ?? []),
            ],
            'total' => [
                'raw_value'       => $data['total'] ?? 0,
                'tooltip'         => '',
                'formatted_value' => $data['total'] ?? '--',
            ],
        ];

        return [
            'slug'    => $data['slug'] ?? 'unknown',
            'state'   => 'active',
            'columns' => $columns,
        ];
    }
}
