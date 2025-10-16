<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-09h-45m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\CustomerSalesChannel\UI;

use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Platform\UI\ShowPlatform;
use App\Actions\Dropshipping\Portfolio\Logs\IndexPlatformPortfolioLogs;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\UI\CRM\CustomerPlatformTabsEnum;
use App\Http\Resources\Dropshipping\PlatformPortfolioLogsResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowCustomerSalesChannel extends OrgAction
{
    use WithCustomerSalesChannelSubNavigation;
    use WithCRMAuthorisation;

    public function handle(CustomerSalesChannel $customerSalesChannel): CustomerSalesChannel
    {
        return $customerSalesChannel;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPlatform(Organisation $organisation, Shop $shop, Platform $platform, CustomerSalesChannel $customerSalesChannel, ActionRequest $request): CustomerSalesChannel
    {
        $this->initialisationFromShop($customerSalesChannel->shop, $request)->withTab(CustomerPlatformTabsEnum::values());
        return $this->handle($customerSalesChannel);
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, CustomerSalesChannel $customerSalesChannel, ActionRequest $request): CustomerSalesChannel
    {
        $this->initialisationFromShop($shop, $request)->withTab(CustomerPlatformTabsEnum::values());
        return $this->handle($customerSalesChannel);
    }


    public function htmlResponse(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): Response
    {
        $navigation = CustomerPlatformTabsEnum::navigation();

        $actions = [];

        $name = $customerSalesChannel->customer->name;
        if (in_array($customerSalesChannel->platform->type, [
            PlatformTypeEnum::SHOPIFY,
            PlatformTypeEnum::WOOCOMMERCE,
            PlatformTypeEnum::TIKTOK
        ])) {
            /** @var ShopifyUser|WooCommerceUser|TiktokUser $platformUser */
            $platformUser = $customerSalesChannel->user;
            if ($platformUser) {
                $name = $platformUser->name;
            }
        }


        return Inertia::render(
            'Org/Dropshipping/CustomerSalesChannel',
            [
                'title'       => __('customer'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $customerSalesChannel,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    ...$this->getCustomerSalesChannelSubNavigationHead($customerSalesChannel),
                    'actions' => $actions
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],

                'showcase' => [
                    'stats' => [
                        'name'                    => $name,
                        'number_orders'           => $customerSalesChannel->number_orders,
                        'number_customer_clients' => $customerSalesChannel->number_customer_clients,
                        'number_portfolios'       => $customerSalesChannel->number_portfolios,
                    ],
                    'platform'               => $customerSalesChannel->platform,
                    'customer_sales_channel' => $customerSalesChannel,
                    'platform_user'          => $customerSalesChannel->user,
                ],
                'logs' => PlatformPortfolioLogsResource::collection(IndexPlatformPortfolioLogs::run($customerSalesChannel))
            ]
        )->table(IndexPlatformPortfolioLogs::make()->tableStructure(null, 'logs'));
    }

    public function getBreadcrumbs(CustomerSalesChannel $customerSalesChannel, string $routeName, array $routeParameters): array
    {

        $headCrumb = function (CustomerSalesChannel $customerSalesChannel, array $routeParameters, string $suffix = '') {
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
                            'label' => $customerSalesChannel->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.crm.platforms.show.customer_sales_channels.show' =>
            array_merge(
                ShowPlatform::make()->getBreadcrumbs(
                    $routeName,
                    $routeParameters
                ),
                $headCrumb(
                    $customerSalesChannel,
                    [

                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'customer'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show',
                            'parameters' => $routeParameters
                        ]
                    ]
                )
            ),
            default => array_merge(
                ShowCustomer::make()->getBreadcrumbs(
                    $routeName,
                    $routeParameters
                ),
                $headCrumb(
                    $customerSalesChannel,
                    [

                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'customer'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show',
                            'parameters' => $routeParameters
                        ]
                    ]
                )
            ),
        };
    }
}
