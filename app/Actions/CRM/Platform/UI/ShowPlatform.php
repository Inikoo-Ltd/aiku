<?php

/*
 * author Arya Permana - Kirin
 * created on 10-03-2025-11h-23m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\CRM\Platform\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Dropshipping\CustomerSalesChannel\UI\IndexCustomerSalesChannels;
use App\Actions\Dropshipping\Portfolio\UI\IndexPortfoliosInPlatform;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Enums\UI\CRM\PlatformTabsEnum;
use App\Http\Resources\CRM\CustomerSalesChannelsResourcePro;
use App\Http\Resources\CRM\PortfoliosResource;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Organisation;
use Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPlatform extends OrgAction
{
    use WithCRMAuthorisation;

    private Shop $parent;

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

    public function htmlResponse(Platform $platform, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Shop/CRM/Platform',
            [
                'title'       => __('platform'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'     => $platform->name,
                    'model'     => __('platform'),
                    'icon'      =>
                        [
                            'icon'  => ['fal', 'fa-code-branch'],
                            'title' => __('platform')
                        ]
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => PlatformTabsEnum::navigation()
                ],

                PlatformTabsEnum::CHANNELS->value => $this->tab == PlatformTabsEnum::CHANNELS->value ?
                    fn() => CustomerSalesChannelsResourcePro::collection(IndexCustomerSalesChannels::run($platform, prefix: PlatformTabsEnum::CHANNELS->value))
                    : Inertia::lazy(fn() => CustomerSalesChannelsResourcePro::collection(IndexCustomerSalesChannels::run($platform, prefix: PlatformTabsEnum::CHANNELS->value))),

                PlatformTabsEnum::PRODUCTS->value => $this->tab == PlatformTabsEnum::PRODUCTS->value ?
                    fn() => PortfoliosResource::collection(IndexPortfoliosInPlatform::run($platform, prefix: PlatformTabsEnum::PRODUCTS->value))
                    : Inertia::lazy(fn() => PortfoliosResource::collection(IndexPortfoliosInPlatform::run($platform, prefix: PlatformTabsEnum::PRODUCTS->value))),

            ]
        )->table(
                IndexCustomerSalesChannels::make()->tableStructure(
                    prefix: PlatformTabsEnum::CHANNELS->value,
                )
        )->table(
                IndexPortfoliosInPlatform::make()->tableStructure(
                    prefix: PlatformTabsEnum::PRODUCTS->value,
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
                            'label' => $platform->slug,
                        ],
                    ],
                    'suffix'         => $suffix,

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
            default => []
        };
    }
}
