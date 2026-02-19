<?php

/*
 * author Arya Permana - Kirin
 * created on 10-03-2025-11h-23m
 * github: https://github.com/KirinZero0
 * copyright 2025
 */

namespace App\Actions\CRM\Platform\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Dropshipping\Customers\UI\IndexCustomers;
use App\Actions\Dropshipping\CustomerSalesChannel\UI\IndexCustomerSalesChannels;
use App\Actions\Dropshipping\Invoices\UI\IndexInvoices;
use App\Actions\Dropshipping\Portfolio\UI\IndexPortfoliosInPlatform;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\UI\CRM\PlatformTabsEnum;
use App\Http\Resources\CRM\CustomerSalesChannelsResource;
use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\CRM\InvoicesResource;
use App\Http\Resources\CRM\PortfoliosResource;
use App\Http\Resources\CRM\TopListedProductsResource;
use App\Http\Resources\CRM\TopSoldProductsResource;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPlatform extends OrgAction
{
    use WithCRMAuthorisation;

    private Group|Shop $parent;

    public function handle(Platform $platform): Platform
    {
        return $platform;
    }

    public function asController(Organisation $organisation, Shop $shop, Platform $platform, ActionRequest $request): Platform
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(PlatformTabsEnum::values());

        return $this->handle($platform);
    }

    public function inGroup(Platform $platform, ActionRequest $request): Platform
    {
        $this->parent = app('group');
        $this->initialisationFromGroup(app('group'), $request)->withTab(PlatformTabsEnum::values());

        return $this->handle($platform);
    }

    public function htmlResponse(Platform $platform, ActionRequest $request): Response
    {
        $parent = $this->parent;

        return Inertia::render(
            'Org/Shop/CRM/Platform',
            [
                'title'       => __('Platform'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'pageHead'    => [
                    'title' => $platform->name,
                    'model' => __('Platform'),
                    'icon'  =>
                        [
                            'icon'  => ['fal', 'fa-code-branch'],
                            'title' => __('Platform')
                        ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => PlatformTabsEnum::navigation()
                ],
                PlatformTabsEnum::SHOWCASE->value =>
                    $this->tab == PlatformTabsEnum::SHOWCASE->value
                        ? fn () => InvoicesResource::collection(IndexInvoices::run($parent, $platform, prefix: PlatformTabsEnum::SHOWCASE->value))
                        : Inertia::lazy(fn () => InvoicesResource::collection(IndexInvoices::run($parent, $platform, prefix: PlatformTabsEnum::SHOWCASE->value))),
                PlatformTabsEnum::CHANNELS->value =>
                    $this->tab == PlatformTabsEnum::CHANNELS->value
                        ? fn () => CustomerSalesChannelsResource::collection(IndexCustomerSalesChannels::run(parent: $platform, shop: $parent instanceof Shop ? $parent : null, prefix: PlatformTabsEnum::CHANNELS->value))
                        : Inertia::lazy(fn () => CustomerSalesChannelsResource::collection(IndexCustomerSalesChannels::run(parent: $platform, shop: $parent instanceof Shop ? $parent : null, prefix: PlatformTabsEnum::CHANNELS->value))),
                PlatformTabsEnum::CUSTOMERS->value =>
                    $this->tab == PlatformTabsEnum::CUSTOMERS->value
                        ? fn () => CustomersResource::collection(IndexCustomers::run($parent, $platform, prefix: PlatformTabsEnum::CUSTOMERS->value))
                        : Inertia::lazy(fn () => IndexCustomers::run($parent, $platform, prefix: PlatformTabsEnum::CUSTOMERS->value)),
                PlatformTabsEnum::PRODUCTS->value =>
                    $this->tab == PlatformTabsEnum::PRODUCTS->value
                        ? fn () => PortfoliosResource::collection(IndexPortfoliosInPlatform::run($parent, $platform, prefix: PlatformTabsEnum::PRODUCTS->value))
                        : Inertia::lazy(fn () => PortfoliosResource::collection(IndexPortfoliosInPlatform::run($parent, $platform, prefix: PlatformTabsEnum::PRODUCTS->value))),
                PlatformTabsEnum::TOP_LISTED_PRODUCTS->value =>
                    $this->tab == PlatformTabsEnum::TOP_LISTED_PRODUCTS->value
                        ? fn () => TopListedProductsResource::collection(IndexTopListedProductsInPlatform::run($parent, $platform, prefix: PlatformTabsEnum::TOP_LISTED_PRODUCTS->value))
                        : Inertia::lazy(fn () => TopListedProductsResource::collection(IndexTopListedProductsInPlatform::run($parent, $platform, prefix: PlatformTabsEnum::TOP_LISTED_PRODUCTS->value))),
                PlatformTabsEnum::TOP_SOLD_PRODUCTS->value =>
                    $this->tab == PlatformTabsEnum::TOP_SOLD_PRODUCTS->value
                        ? fn () => TopSoldProductsResource::collection(IndexTopSoldProductsInPlatform::run($parent, $platform, prefix: PlatformTabsEnum::TOP_SOLD_PRODUCTS->value))
                        : Inertia::lazy(fn () => TopSoldProductsResource::collection(IndexTopSoldProductsInPlatform::run($parent, $platform, prefix: PlatformTabsEnum::TOP_SOLD_PRODUCTS->value))),
            ]
        )->table(
            IndexInvoices::make()->tableStructure(
                prefix: PlatformTabsEnum::SHOWCASE->value,
            )
        )->table(
            IndexCustomerSalesChannels::make()->tableStructure(
                parent: $platform,
                prefix: PlatformTabsEnum::CHANNELS->value,
            )
        )->table(
            IndexCustomers::make()->tableStructure(
                prefix: PlatformTabsEnum::CUSTOMERS->value,
            )
        )->table(
            IndexPortfoliosInPlatform::make()->tableStructure(
                prefix: PlatformTabsEnum::PRODUCTS->value,
            )
        )->table(
            IndexTopListedProductsInPlatform::make()->tableStructure(
                prefix: PlatformTabsEnum::TOP_LISTED_PRODUCTS->value,
            )
        )->table(
            IndexTopSoldProductsInPlatform::make()->tableStructure(
                prefix: PlatformTabsEnum::TOP_SOLD_PRODUCTS->value,
            )
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Platform $platform, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Platforms')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $platform->name,
                        ],
                    ],
                    'suffix' => $suffix,
                ],
            ];
        };

        $platform = Platform::where('slug', $routeParameters['platform'])->first();

        return match ($routeName) {
            'grp.org.shops.show.crm.platforms.show' =>
                array_merge(
                    ShowShop::make()->getBreadcrumbs(Arr::only($routeParameters, ['organisation', 'shop'])),
                    $headCrumb(
                        $platform,
                        [
                            'index' => [
                                'name'       => 'grp.org.shops.show.crm.platforms.index',
                                'parameters' => $routeParameters
                            ],
                            'model' => [
                                'name'       => 'grp.org.shops.show.crm.platforms.show',
                                'parameters' => $routeParameters
                            ]
                        ],
                        $suffix
                    )
                ),
            'grp.platforms.show' =>
                array_merge(
                    ShowGroupDashboard::make()->getBreadcrumbs(),
                    $headCrumb(
                        $platform,
                        [
                            'index' => [
                                'name'       => 'grp.platforms.index',
                                'parameters' => $routeParameters
                            ],
                            'model' => [
                                'name'       => 'grp.platforms.show',
                                'parameters' => $routeParameters
                            ]
                        ],
                        $suffix
                    )
                ),
            default => []
        };
    }
}
