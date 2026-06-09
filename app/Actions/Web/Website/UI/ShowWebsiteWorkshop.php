<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 15:31:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\GetWebsiteWorkshopDepartmentDescriptionWebBlock;
use App\Actions\Web\Website\GetWebsiteWorkshopSubDepartmentWebBlock;
use App\Actions\Web\Website\GetWebsiteWorkshopFamiliesOverviewWebBlock;
use App\Actions\Web\Website\GetWebsiteWorkshopFamilyDescriptionWebBlock;
use App\Actions\Web\Website\GetWebsiteWorkshopProductListWebBlock;
use App\Actions\Web\Website\GetWebsiteWorkshopLayout;
use App\Actions\Web\Website\GetWebsiteWorkshopProduct;
use App\Actions\Web\Website\GetWebsiteWorkshopFamilyWebBlock;
use App\Enums\UI\Web\WebsiteWorkshopTabsEnum;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Arr;

class ShowWebsiteWorkshop extends OrgAction
{
    use WithWebAuthorisation;

    private Fulfilment|Shop $parent;
    private Fulfilment|Shop $scope;

    public function handle(Website $website): Website
    {
        return $website;
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): Website
    {
        $this->scope  = $shop;
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(WebsiteWorkshopTabsEnum::values());

        return $website;
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): Website
    {
        $this->scope  = $fulfilment;
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(WebsiteWorkshopTabsEnum::values());

        return $this->handle($website);
    }


    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        $product = $website->shop->productsInStock()->first();

        $navigation = WebsiteWorkshopTabsEnum::navigation();

        if ($this->scope instanceof Fulfilment) {
            $navigation = WebsiteWorkshopTabsEnum::navigationExcept([
                WebsiteWorkshopTabsEnum::PRODUCT,
                WebsiteWorkshopTabsEnum::PRODUCTS,
                WebsiteWorkshopTabsEnum::SUB_DEPARTMENT,
                WebsiteWorkshopTabsEnum::FAMILY,
                WebsiteWorkshopTabsEnum::FAMILIES_OVERVIEW,
                WebsiteWorkshopTabsEnum::HISTORY,
            ]);
        }

        $tabs = [
            WebsiteWorkshopTabsEnum::WEBSITE_LAYOUT->value => $this->tab == WebsiteWorkshopTabsEnum::WEBSITE_LAYOUT->value ?
                fn () => GetWebsiteWorkshopLayout::run($this->scope, $website)
                : Inertia::lazy(fn () => GetWebsiteWorkshopLayout::run($this->scope, $website)),
        ];

        $tabs[WebsiteWorkshopTabsEnum::DEPARTMENT_DESCRIPTION->value] = $this->tab == WebsiteWorkshopTabsEnum::DEPARTMENT_DESCRIPTION->value
            ? 
            fn () => GetWebsiteWorkshopDepartmentDescriptionWebBlock::run($website)
            : Inertia::lazy(
                fn () => GetWebsiteWorkshopDepartmentDescriptionWebBlock::run($website)
            );
        
        $tabs[WebsiteWorkshopTabsEnum::SUB_DEPARTMENT->value] = $this->tab == WebsiteWorkshopTabsEnum::SUB_DEPARTMENT->value
            ?
            fn () => GetWebsiteWorkshopSubDepartmentWebBlock::run($website)
            : Inertia::lazy(
                fn () => GetWebsiteWorkshopSubDepartmentWebBlock::run($website)
            );

        $tabs[WebsiteWorkshopTabsEnum::FAMILY->value] = $this->tab == WebsiteWorkshopTabsEnum::FAMILY->value
            ?
            fn () => GetWebsiteWorkshopFamilyWebBlock::run($website)
            : Inertia::lazy(
                fn () => GetWebsiteWorkshopFamilyWebBlock::run($website)
            );

        $tabs[WebsiteWorkshopTabsEnum::FAMILIES_OVERVIEW->value] = $this->tab == WebsiteWorkshopTabsEnum::FAMILIES_OVERVIEW->value
            ?
            fn () => GetWebsiteWorkshopFamiliesOverviewWebBlock::run($website)
            : Inertia::lazy(
                fn () => GetWebsiteWorkshopFamiliesOverviewWebBlock::run($website)
            );

        $tabs[WebsiteWorkshopTabsEnum::FAMILIES_DESCRIPTION->value] = $this->tab == WebsiteWorkshopTabsEnum::FAMILIES_DESCRIPTION->value
            ?
            fn () => GetWebsiteWorkshopFamilyDescriptionWebBlock::run($website)
            : Inertia::lazy(
                fn () => GetWebsiteWorkshopFamilyDescriptionWebBlock::run($website)
            );

        $tabs[WebsiteWorkshopTabsEnum::PRODUCTS->value] = $this->tab == WebsiteWorkshopTabsEnum::PRODUCTS->value
            ?
            fn () => GetWebsiteWorkshopProductListWebBlock::run($website)
            : Inertia::lazy(
                fn () => GetWebsiteWorkshopProductListWebBlock::run($website)
            );

        if ($product) {
            $tabs[WebsiteWorkshopTabsEnum::PRODUCT->value] = $this->tab == WebsiteWorkshopTabsEnum::PRODUCT->value
                ?
                fn () => GetWebsiteWorkshopProduct::run($website, $product)
                : Inertia::lazy(
                    fn () => GetWebsiteWorkshopProduct::run($website, $product)
                );
        }

        $tabs[WebsiteWorkshopTabsEnum::HISTORY->value] = $this->tab == WebsiteWorkshopTabsEnum::HISTORY->value
            ?
            fn () => HistoryResource::collection(IndexHistory::run($website, WebsiteWorkshopTabsEnum::HISTORY->value, ['products_published', 'product_published', 'families_overview_published', 'family_published', 'sub_department_published']))
            : Inertia::lazy(
                fn () => HistoryResource::collection(IndexHistory::run($website, WebsiteWorkshopTabsEnum::HISTORY->value, ['products_published', 'product_published', 'families_overview_published', 'family_published', 'sub_department_published']))
            );

        $publishRoute = [
            'method'     => 'patch',
            'name'       => 'grp.models.website.update',
            'parameters' => [
                'website' => $website->id
            ]
        ];

        /*  if ($this->tab == WebsiteWorkshopTabsEnum::SUB_DEPARTMENT->value) {
             $publishRoute = [
                 'method'     => 'post',
                 'name'       => 'grp.models.website.publish.sub_department',
                 'parameters' => [
                     'website' => $website->id
                 ]
             ];
         } elseif ($this->tab == WebsiteWorkshopTabsEnum::FAMILY->value) {
             $publishRoute = [
                 'method'     => 'post',
                 'name'       => 'grp.models.website.publish.family',
                 'parameters' => [
                     'website' => $website->id
                 ]
             ];
         } elseif ($this->tab == WebsiteWorkshopTabsEnum::PRODUCT->value) {
             $publishRoute = [
                 'method'     => 'post',
                 'name'       => 'grp.models.website.publish.product',
                 'parameters' => [
                     'website' => $website->id
                 ]
             ];
         } elseif ($this->tab == WebsiteWorkshopTabsEnum::PRODUCTS->value) {
             $publishRoute = [
                 'method'     => 'post',
                 'name'       => 'grp.models.website.publish.products',
                 'parameters' => [
                     'website' => $website->id
                 ]
             ];
         } */

        return Inertia::render(
            'Org/Web/Workshop/WebsiteWorkshop',
            [
                'title'       => __("Website's workshop"),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                    '('.__('Workshop').')'
                ),
                'pageHead'    => [

                    'title' => __('Workshop'),
                    'model' => __('Website'),
                    'icon'  =>
                        [
                            'icon'  => ['fal', 'drafting-compass'],
                            'title' => __("Website's workshop")
                        ],

                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Exit workshop'),
                            'route' => [
                                'name'       => preg_replace('/workshop$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ]
                        ],
                        [
                            'type'  => 'button',
                            'style' => 'primary',
                            'key'   => 'Publish',
                            'icon'  => ["fas", "fa-save"],
                            'label' => __('Publish'),
                            'route' => $publishRoute
                        ]
                    ],
                ],

                'tabs'     => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],
                'currency'  => $this->parent instanceof Shop ? CurrencyResource::make($this->parent->currency)->resolve() : null,
                'settings' => $website->settings,
                'website_slug' => $website->slug,
                'publishRoute' => [
                    'website_layout' =>  [
                        'method'     => 'patch',
                       /*  'name'       => 'grp.models.website.update', */
                        'name'       => 'grp.models.website.update.theme',
                        'parameters' => [
                            'website' => $website->id
                        ]
                    ],
                    'sub_department' => [
                        'method'     => 'post',
                        'name'       => 'grp.models.website.publish.sub_department',
                        'parameters' => [
                            'website' => $website->id
                        ]
                    ],
                    'families' =>  [
                        'method'     => 'post',
                        'name'       => 'grp.models.website.publish.family',
                        'parameters' => [
                            'website' => $website->id
                        ]
                    ],
                    'families_description' =>  [
                        'method'     => 'post',
                        'name'       => 'grp.models.website.publish.family_description',
                        'parameters' => [
                            'website' => $website->id
                        ]
                    ],
                    'families_overview' =>  [
                        'method'     => 'post',
                        'name'       => 'grp.models.website.publish.families_overview',
                        'parameters' => [
                            'website' => $website->id
                        ]
                    ],
                    'product' => [
                        'method'     => 'post',
                        'name'       => 'grp.models.website.publish.product',
                        'parameters' => [
                            'website' => $website->id
                        ]
                    ],
                    'products' =>  [
                        'method'     => 'post',
                        'name'       => 'grp.models.website.publish.products',
                        'parameters' => [
                            'website' => $website->id
                        ]
                    ],
                ],
                'layout_theme' => Arr::get($website->published_layout, 'theme'),
                ...$tabs
            ]
        )
        ->table(IndexHistory::make()->tableStructure(WebsiteWorkshopTabsEnum::HISTORY->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {
        /** @var Website $website */
        $website = request()->route()->parameter('website');

        return match ($routeName) {
            'grp.org.shops.show.web.websites.workshop',
            'grp.org.shops.show.web.websites.workshop.header',
            'grp.org.shops.show.web.websites.workshop.menu',
            'grp.org.shops.show.web.websites.workshop.footer' =>


            ShowWebsite::make()->getBreadcrumbs($website, 'grp.org.shops.show.web.websites.show', $routeParameters, suffix:$suffix),


            'grp.org.fulfilments.show.web.websites.workshop' =>

            ShowWebsite::make()->getBreadcrumbs($website, 'grp.org.fulfilments.show.web.websites.show', $routeParameters, suffix:$suffix),


            default => []
        };
    }


}
