<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Mar 2025 21:03:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Dashboards\Settings;

use Illuminate\Support\Arr;

trait WithDashboardShopStateTypeSettings
{
    public function dashboardShopStateTypeSettings(array $settings, string $align = 'right'): array
    {
        $id = 'shop_state';

        return [
            'id'      => $id,
            'display' => true,
            'align'   => $align,
            'type'    => 'toggle',
            'value'   => Arr::get($settings, $id, 'open'),
            'options' => [
                [
                    'value'   => 'open',
                    'label'   => __('Open only'),
                    'tooltip' => __('Only show shops that are open')
                ],
                [
                    'value'   => 'closed',
                    'label'   => __('All shops including closed'),
                    'tooltip' => __('Show all shops including closed')
                ]
            ]
        ];
    }

}
