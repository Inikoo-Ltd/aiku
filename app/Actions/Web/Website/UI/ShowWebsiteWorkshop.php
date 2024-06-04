<?php
/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 15:31:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\HasWebAuthorisation;
use App\Actions\Web\Website\GetWebsiteWorkshopCategory;
use App\Actions\Web\Website\GetWebsiteWorkshopColorScheme;
use App\Actions\Web\Website\GetWebsiteWorkshopLayout;
use App\Actions\Web\Website\GetWebsiteWorkshopMenu;
use App\Actions\Web\Website\GetWebsiteWorkshopProduct;
use App\Enums\UI\Web\WebsiteWorkshopTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWebsiteWorkshop extends OrgAction
{
    use HasWebAuthorisation;

    private Fulfilment|Shop $parent;

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
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($website);
    }


    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Web/WebsiteWorkshop',
            [
                'title'       => __("Website's workshop"),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [

                    'title'     => __('Workshop'),
                    'iconRight' =>
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
                        ]
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => WebsiteWorkshopTabsEnum::navigation(),
                ],

                WebsiteWorkshopTabsEnum::COLOR_SCHEME->value => $this->tab == WebsiteWorkshopTabsEnum::COLOR_SCHEME->value
                    ?
                    fn () => GetWebsiteWorkshopColorScheme::run($website)
                    : Inertia::lazy(
                        fn () => GetWebsiteWorkshopColorScheme::run($website)
                    ),

                WebsiteWorkshopTabsEnum::MENU->value => $this->tab == WebsiteWorkshopTabsEnum::MENU->value
                    ?
                    fn () => GetWebsiteWorkshopMenu::run($website)
                    : Inertia::lazy(fn () => GetWebsiteWorkshopMenu::run($website)),

                WebsiteWorkshopTabsEnum::LAYOUT->value => $this->tab == WebsiteWorkshopTabsEnum::LAYOUT->value ?
                    fn () => GetWebsiteWorkshopLayout::run($this->scope, $website)
                    : Inertia::lazy(fn () => GetWebsiteWorkshopLayout::run($this->scope, $website)),

                WebsiteWorkshopTabsEnum::CATEGORY->value => $this->tab == WebsiteWorkshopTabsEnum::CATEGORY->value
                    ?
                    fn () => GetWebsiteWorkshopCategory::run($website)
                    : Inertia::lazy(
                        fn () => GetWebsiteWorkshopCategory::run($website)
                    ),

                WebsiteWorkshopTabsEnum::PRODUCT->value => $this->tab == WebsiteWorkshopTabsEnum::PRODUCT->value
                    ?
                    fn () => GetWebsiteWorkshopProduct::run($website)
                    : Inertia::lazy(
                        fn () => GetWebsiteWorkshopProduct::run($website)
                    ),

            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (string $type, Website $website, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => $type,
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Websites')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $website->name,
                        ],

                    ],
                    'simple'         => [
                        'route' => $routeParameters['model'],
                        'label' => $website->name
                    ],


                    'suffix' => $suffix

                ],
            ];
        };

        $website = Website::where('slug', $routeParameters['website'])->first();

        return match ($routeName) {
            'grp.org.shops.show.web.websites.workshop' =>

            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    'modelWithIndex',
                    $website,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.web.websites.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.web.websites.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.fulfilments.show.web.websites.workshop' =>
                array_merge(
                    ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                    $headCrumb(
                        'modelWithIndex',
                        $website,
                        [
                            'index' => [
                                'name'       => 'grp.org.fulfilments.show.web.websites.index',
                                'parameters' => $routeParameters
                            ],
                            'model' => [
                                'name'       => 'grp.org.fulfilments.show.web.websites.show',
                                'parameters' => $routeParameters
                            ]
                        ],
                        $suffix
                    ),
                ),

            default => []
        };
    }



}
