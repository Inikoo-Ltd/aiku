<?php

namespace App\Actions\Traits\Dashboards\Settings;

use Illuminate\Support\Arr;

trait WithDashboardPartnersTypeSettings
{
    public function dashboardPartnersTypeSettings(array $settings, string $align = 'left'): array
    {
        $id = 'partners_type';

        return [
            'id'      => $id,
            'display' => true,
            'align'   => $align,
            'type'    => 'toggle',
            'value'   => Arr::get($settings, $id, 'external'),
            'options' => [
                [
                    'value'   => 'external',
                    'label'   => __('Without partners'),
                    'tooltip' => __('Leave out sales to our own companies')
                ],
                [
                    'value'   => 'all',
                    'label'   => __('With partners'),
                    'tooltip' => __('Include sales to our own companies')
                ]
            ]
        ];
    }

    public function dashboardIncludesPartners(array $settings): bool
    {
        return Arr::get($settings, 'partners_type') === 'all';
    }
}
