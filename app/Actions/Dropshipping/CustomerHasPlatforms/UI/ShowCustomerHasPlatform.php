<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-09h-45m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\CustomerHasPlatforms\UI;

use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
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

class ShowCustomerHasPlatform extends OrgAction
{
    use WithCustomerHasPlatformSubNavigation;
    use WithCRMAuthorisation;

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
            'Org/Dropshipping/PlatformInCustomer',
            [
                'title'       => __('customer'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $customerHasPlatform->platform,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-user'],
                        'title' => __('customer')
                    ],

                    'subNavigation' => $this->getCustomerPlatformSubNavigation($customerHasPlatform, $request),
                    'title'         => $customerHasPlatform->customer->name.' ('.$customerHasPlatform->customer->reference.')',
                    'afterTitle'    => [
                        'label' => ' @'.$customerHasPlatform->platform->name,
                    ],
                    'actions'       => $actions
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],

                'showcase' => [
                    'stats' => [
                        'name' => match ($customerHasPlatform->platform->type) {
                            PlatformTypeEnum::SHOPIFY => $customerHasPlatform->customer->shopifyUser->name,
                            PlatformTypeEnum::WOOCOMMERCE => $customerHasPlatform->customer->wooCommerceUser->name,
                            PlatformTypeEnum::TIKTOK => $customerHasPlatform->customer->tiktokUser->name,
                            default => $customerHasPlatform->customer->name,
                        },
                        'number_orders' => $customerHasPlatform->number_orders,
                        'number_customer_clients' => $customerHasPlatform->number_customer_clients,
                        'number_portfolios' => $customerHasPlatform->number_portfolios
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(Platform $platform, string $routeName, array $routeParameters): array
    {
        $headCrumb = function (Platform $platform, array $routeParameters, string $suffix = '') {
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
                            'label' => $platform->name,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };


        return array_merge(
            ShowCustomer::make()->getBreadcrumbs(
                $routeName,
                $routeParameters
            ),
            $headCrumb(
                $platform,
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
