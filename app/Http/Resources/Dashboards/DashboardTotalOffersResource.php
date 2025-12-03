<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 19 Nov 2025 14:52:52 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Enums\DateIntervals\DateIntervalEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

class DashboardTotalOffersResource extends JsonResource
{
    use WithDashboardIntervalValues;

    public function toArray($request): array
    {
        $models = $this->resource;

        //        $summedData = (object) array_merge(
        //            $this->sumIntervalValues($models, 'customers'),
        //            $this->sumIntervalValues($models, 'orders')
        //        );

        $summedData = array_merge(
            $this->sumIntervalValuesFromArray($models, 'customers'),
            $this->sumIntervalValuesFromArray($models, 'orders')
        );

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => 'Total',
                    'align'           => 'left',
                ],
            ],
            //            $this->getDashboardTableColumn($summedData, 'customers'),
            //            $this->getDashboardTableColumn($summedData, 'orders')
            $this->getDashboardTableColumnFromArray($summedData, 'customers'),
            $this->getDashboardTableColumnFromArray($summedData, 'orders')
        );

        return [
            'slug'    => 'totals',
            'columns' => $columns,
        ];
    }

    private function sumIntervalValuesFromArray(array $models, string $field): array
    {
        $sums = [];

        foreach (DateIntervalEnum::cases() as $interval) {
            $key = $field . '_' . $interval->value;
            $sums[$key] = 0;

            foreach ($models as $model) {
                $sums[$key] += $model[$key] ?? 0;
            }
        }

        return $sums;
    }

    private function getDashboardTableColumnFromArray(array $summedData, string $scope): array
    {
        $intervals = DateIntervalEnum::cases();
        $columns = [];

        foreach ($intervals as $interval) {
            $key = $scope . '_' . $interval->value;
            $rawValue = $summedData[$key] ?? 0;

            $columns[$interval->value] = [
                'raw_value' => $rawValue,
                'tooltip' => '',
                'formatted_value' => Number::format($rawValue)
            ];
        }

        return [$scope => $columns];
    }
}
