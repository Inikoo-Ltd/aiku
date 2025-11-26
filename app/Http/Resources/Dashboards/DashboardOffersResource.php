<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 19 Nov 2025 12:48:35 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Enums\DateIntervals\DateIntervalEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

class DashboardOffersResource extends JsonResource
{
    use WithDashboardIntervalValues;

    public function toArray($request): array
    {
        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => 'Dummy',
                    'align'           => 'left'
                ]
            ],
            //            $this->getDashboardTableColumn($this, 'customers'),
            //            $this->getDashboardTableColumn($this, 'orders')
            $this->getDashboardTableColumnFromArray('customers'),
            $this->getDashboardTableColumnFromArray('orders')
        );

        return [
            'slug'      => 'dummy',
            'state'     => 'active',
            'columns'   => $columns,
            'colour'    => ''
        ];
    }

    private function getDashboardTableColumnFromArray(string $scope): array
    {
        $intervals = DateIntervalEnum::casesWithoutCustom();
        $columns = [];

        foreach ($intervals as $interval) {
            $key = "{$scope}_{$interval->value}";
            $rawValue = $this->resource[$key] ?? 0;

            $columns[$interval->value] = [
                'raw_value' => $rawValue,
                'tooltip' => '',
                'formatted_value' => Number::format($rawValue)
            ];
        }

        return [$scope => $columns];
    }
}
