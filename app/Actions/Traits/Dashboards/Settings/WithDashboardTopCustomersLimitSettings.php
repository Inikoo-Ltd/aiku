<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Traits\Dashboards\Settings;

use Illuminate\Support\Arr;

trait WithDashboardTopCustomersLimitSettings
{
    public function dashboardTopCustomersLimitSettings(array $settings, string $align = 'right'): array
    {
        $id = 'top_customers_limit';

        $value = (int) Arr::get($settings, $id, 10);

        if (!in_array($value, [3, 10, 50, 100], true)) {
            $value = 10;
        }

        return [
            'id'      => $id,
            'display' => true,
            'align'   => $align,
            'type'    => 'select',
            'value'   => $value,
            'options' => [
                [
                    'value'   => 3,
                    'label'   => '3',
                    'tooltip' => __('Show top 3 customers'),
                ],
                [
                    'value'   => 10,
                    'label'   => '10',
                    'tooltip' => __('Show top 10 customers'),
                ],
                [
                    'value'   => 50,
                    'label'   => '50',
                    'tooltip' => __('Show top 50 customers'),
                ],
                [
                    'value'   => 100,
                    'label'   => '100',
                    'tooltip' => __('Show top 100 customers'),
                ],
            ],
        ];
    }
}
