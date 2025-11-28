<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 19 Nov 2025 12:19:21 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Models\Catalogue\Shop;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderOffersResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Shop $model */
        $model = $this->resource;

        return [
            'slug'    => $model->slug,
            'columns' => [
                'label'     => [
                    'formatted_value'   => __('Bracket'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'left'
                ],
                'customers' => [
                    'formatted_value'   => __('Customers'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'left',
                    'scope'             => 'customers'
                ],
                'orders'    => [
                    'formatted_value'   => __('Orders'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'left',
                    'scope'             => 'orders'
                ],
            ]
        ];
    }
}
