<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 19 Nov 2025 12:48:35 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardOffersResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $data = (array) $this->resource;

        // Method 1: One by one
        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $data['name'] ?? $data['code'] ?? 'Unknown',
                    'align'           => 'left'
                ]
            ],
            $this->getDashboardTableColumnFromArray($data, 'customers'),
            $this->getDashboardTableColumnFromArray($data, 'customers_minified'),
            $this->getDashboardTableColumnFromArray($data, 'orders'),
            $this->getDashboardTableColumnFromArray($data, 'orders_minified'),
            $this->getDashboardTableColumnFromArray($data, 'orders_delta')
        );

        // Method 2: Batch (alternative, cleaner)
        // $columns = array_merge(
        //     [
        //         'label' => [
        //             'formatted_value' => $data['name'] ?? $data['code'] ?? 'Unknown',
        //             'align'           => 'left'
        //         ]
        //     ],
        //     $this->getDashboardColumnsFromArray($data, [
        //         'customers',
        //         'customers_minified',
        //         'orders',
        //         'orders_minified',
        //         'orders_delta'
        //     ])
        // );

        return [
            'slug'    => $data['slug'] ?? 'unknown',
            'state'   => $data['state'] ?? 'active',
            'columns' => $columns,
            'colour'  => ''
        ];
    }
}
