<?php

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalDispatchHubResource extends JsonResource
{
    public function toArray($request): array
    {
        $collection = collect($this->resource);

        $totals = [
            'todo'             => $collection->sum('todo'),
            'queued'           => $collection->sum('queued'),
            'handling'         => $collection->sum('handling'),
            'handling_blocked' => $collection->sum('handling_blocked'),
            'picked'           => $collection->sum('picked'),
            'packing'          => $collection->sum('packing'),
            'packed'           => $collection->sum('packed'),
            'finalised'        => $collection->sum('finalised'),
            'total'            => $collection->sum('total'),
        ];

        $columns = [
            'label' => [
                'formatted_value' => 'Total',
                'align'           => 'left',
            ],
        ];

        foreach ($totals as $key => $value) {
            $columns[$key] = [
                'raw_value'       => $value,
                'tooltip'         => '',
                'formatted_value' => $value,
            ];
        }

        return [
            'slug'    => 'total',
            'state'   => 'active',
            'columns' => $columns,
        ];
    }
}
