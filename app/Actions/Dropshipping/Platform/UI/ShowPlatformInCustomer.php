<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-09h-45m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Platform\UI;

use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\WithCustomerPlatformSubNavigation;
use App\Actions\OrgAction;
use App\Enums\UI\CRM\CustomerPlatformTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPlatformInCustomer extends OrgAction
{
    use WithCustomerPlatformSubNavigation;

    public function handle(CustomerHasPlatform $customerHasPlatform): CustomerHasPlatform
    {
        return $customerHasPlatform;
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, Platform $platform, ActionRequest $request): CustomerHasPlatform
    {
        $this->initialisationFromShop($shop, $request)->withTab(CustomerPlatformTabsEnum::values());

        $customerHasPlatform = CustomerHasPlatform::where('customer_id', $customer->id)->where('platform_id', $platform->id)->first();


        return $this->handle($customerHasPlatform);
    }

    public function htmlResponse(CustomerHasPlatform $customerHasPlatform, ActionRequest $request): Response
    {
        $navigation = CustomerPlatformTabsEnum::navigation();

        $actions = [];

        return Inertia::render(
            'Org/Fulfilment/FulfilmentCustomerPlatform',
            [
                'title'       => __('customer'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $customerHasPlatform,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'icon'          => [
                        'title' => __('platform'),
                        'icon'  => 'fal fa-user',
                    ],
                    'model'         => __('Platform xx'),
                    'subNavigation' => $this->getCustomerPlatformSubNavigation($customerHasPlatform, $request),
                    'title'         => $customerHasPlatform->platform->name,
                    'afterTitle'    => [
                        'label' => '('.$customerHasPlatform->customer->name.')',
                    ],
                    'actions'       => $actions
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],
            ]
        );
    }

    public function getBreadcrumbs(CustomerHasPlatform $customerHasPlatform, string $routeName, array $routeParameters): array
    {
        $headCrumb = function (Customer $customer, array $routeParameters, string $suffix = '') {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Channels')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $customer->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        $customer = $customerHasPlatform->customer;

        return array_merge(
            ShowCustomer::make()->getBreadcrumbs(
                $routeName,
                $routeParameters
            ),
            $headCrumb(
                $customer,
                [

                    'index' => [
                        'name'       => 'grp.org.shops.show.crm.customers.show.platforms.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'customer'])
                    ],
                    'model' => [
                        'name'       => 'grp.org.shops.show.crm.customers.show.platforms.show',
                        'parameters' => $routeParameters
                    ]
                ]
            )
        );
    }
}
