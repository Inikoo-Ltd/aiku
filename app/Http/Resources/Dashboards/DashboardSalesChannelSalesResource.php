<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use App\Actions\Utils\Abbreviate;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardSalesChannelSalesResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $data = (array) $this->resource;

        $columns = [
            'label' => [
                'formatted_value' => $data['name'] ?? 'Unknown',
                'align'           => 'left'
            ],
            'label_minified' => [
                'formatted_value' => Abbreviate::run($data['name'] ?? 'Unknown'),
                'tooltip'         => $data['name'] ?? 'Unknown',
                'align'           => 'left'
            ]
        ];

        $columns = array_merge(
            $columns,
            $this->getDashboardColumnsFromArray($data, [
                'refunds',
                'refunds_minified',
                'refunds_delta',
                'invoices',
                'invoices_minified',
                'invoices_delta',
                'sales_grp_currency',
                'sales_grp_currency_minified',
                'sales_grp_currency_delta',
            ])
        );

        return [
            'slug'    => $data['slug'] ?? 'unknown',
            'state'   => 'active',
            'columns' => $columns
        ];
    }
}
