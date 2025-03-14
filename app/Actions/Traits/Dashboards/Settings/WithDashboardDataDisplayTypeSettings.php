<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Mar 2025 21:04:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Dashboards\Settings;

use Illuminate\Support\Arr;

trait WithDashboardDataDisplayTypeSettings
{
    public function dashboardDataDisplayTypeSettings(array $settings, string $align = 'right'): array
    {
        $id = 'data_display_type';

        return [
            'id'      => $id,
            'align'   => $align,
            'type'    => 'toggle',
            'value'   => Arr::get($settings, $id, 'full'),
            'options' => [
                [
                    'value' => 'minified',
                    'label' => __('Minified')
                ],
                [
                    'value' => 'full',
                    'label' => __('Full')
                ]
            ]
        ];
    }

}
