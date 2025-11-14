<?php

/*
 * author Arya Permana - Kirin
 * created on 23-12-2024-16h-12m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\CRM\Customer\UI;

use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\UI\CRM\CustomersTabsEnum;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCustomersDashboard
{
    use AsObject;

    public function handle(Shop $parent): array
    {
        $stats = [];

        $stats['customers'] = [
            'label' => __('Customers'),
            'count' => $parent->crmStats->number_customers
        ];
        foreach (CustomerStateEnum::cases() as $case) {
            $stats['customers']['cases'][$case->value] = [
                'value' => $case->value,
                'icon'  => CustomerStateEnum::stateIcon()[$case->value],
                'count' => CustomerStateEnum::count($parent)[$case->value],
                'label' => CustomerStateEnum::labels()[$case->value],
                'route' => [
                    'name' => 'grp.org.shops.show.crm.customers.index',
                    'parameters' => [
                        'organisation' => $parent->organisation->slug,
                        'shop'         => $parent->slug,
                        'customers_elements[state]' => $case->value,
                        'tab'          => CustomersTabsEnum::CUSTOMERS->value
                    ]
                ]
            ];
        }

        return array_merge(
            ['prospectStats' => $stats],
            GetCustomerRfmComparison::run($parent->id) ?? []
        );
    }
}
