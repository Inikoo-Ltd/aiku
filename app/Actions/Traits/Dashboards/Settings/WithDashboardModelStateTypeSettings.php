<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Mar 2025 21:03:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Dashboards\Settings;

use Illuminate\Support\Arr;

trait WithDashboardModelStateTypeSettings
{
    public function dashboardModelStateTypeSettings(array $settings, string $align = 'right'): array
    {
        $id = 'model_state';

        return [
            'id'      => $id,
            'display' => true,
            'align'   => $align,
            'type'    => 'toggle',
            'value'   => Arr::get($settings, $id, 'open'),
            'options' => [
                [
                    'value'   => 'open',
                    'label'   => __('Active only'),
                    'tooltip' => __('Only show active')
                ],
                [
                    'value'   => 'closed',
                    'label'   => __('Show all'),
                    'tooltip' => __('Show all shops including inactive ones')
                ]
            ]
        ];
    }

}
