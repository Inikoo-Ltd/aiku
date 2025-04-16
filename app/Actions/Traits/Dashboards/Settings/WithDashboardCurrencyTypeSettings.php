<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Mar 2025 21:04:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Dashboards\Settings;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;

trait WithDashboardCurrencyTypeSettings
{
    public function dashboardCurrencyTypeSettings(Group|Organisation $scope, array $settings, string $align = 'right'): array
    {
        if ($scope instanceof Organisation) {
            $id    = 'scope_org_currency_type';
            $value = Arr::get($settings, $id, 'org');
        } else {
            $id    = 'scope_group_currency_type';
            $value = Arr::get($settings, $id, 'grp');
        }

        $options = $this->getOptions($scope, $settings);


        return [
            'display' => count($options) > 1,
            'id'      => $id,
            'align'   => $align,
            'type'    => 'radio',
            'value'   => $value,
            'options' => $options
        ];
    }

    private function getOptions(Group|Organisation $scope, array $settings): array
    {
        if ($scope instanceof Organisation) {
            return $this->getOrganisationOptions($scope, $settings);
        } else {
            return $this->getGroupOptions($scope);
        }
    }

    private function getOrganisationOptions(Organisation $organisation, array $settings): array
    {
        $shopState = Arr::get($settings, 'shop_state', 'open');
        if ($shopState == 'open') {
            $currencyIds = $organisation->shops()->whereIn('state', [
                ShopStateEnum::OPEN,
                ShopStateEnum::CLOSING_DOWN
            ])->pluck('currency_id')->unique()->toArray();
        } else {
            $currencyIds = $organisation->shops()->pluck('currency_id')->unique()->toArray();
        }
        $currencies      = [];
        $currencySymbols = [];
        foreach ($currencyIds as $currencyId) {
            $currency          = Currency::find($currencyId);
            $currencies[]      = $currency;
            $currencySymbols[] = $currency->symbol;
        }

        $options = [
            [
                'value'   => 'org',
                'label'   => $organisation->currency->symbol,
                'tooltip' => __('Organisation currency'),
            ]
        ];

        if (count($currencies) == 1 && $currencies[0]->id == $organisation->currency_id) {
            return $options;
        }


        $options[] = [
            'value'   => 'shop',
            'label'   => implode(', ', $currencySymbols),
            'tooltip' => __('Shop currencies'),
        ];

        return $options;
    }

    private function getGroupOptions(Group $group): array
    {
        return [
            [
                'value'   => 'org',
                'label'   => '',
                'tooltip' => __('Organisations currency'),
            ],
            [
                'value'   => 'grp',
                'label'   => $group->currency->symbol,
                'tooltip' => __('Group currency'),
            ]
        ];
    }

}
