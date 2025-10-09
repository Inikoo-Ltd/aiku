<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 12 Sep 2025 15:38:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\Website\GetWebsiteWorkshopSidebar;
use App\Actions\Web\Website\UI\ShowWebsiteWorkshop;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Web\WebBlockTypesResource;
use Illuminate\Support\Arr;

class ShowSidebarWorkshop extends OrgAction
{
    use WithMenuSubNavigation;
    use WithWebEditAuthorisation;
    use WithWebsiteWorkshop;


    private Webpage|Website $parent;

    public function handle(Website $website): Website
    {
        return $website;
    }

    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        $sidebarLayout   = Arr::get($website->published_layout, 'sidebar');
        $isMenuActive = Arr::get($sidebarLayout, 'status');
        return Inertia::render(
            'Org/Web/Workshop/Sidebar/MenuWorkshopForSidebar',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __("Website Sidebar's Workshop"),
                'pageHead'    => [
                    'subNavigation' => $this->getMenuSubNavigation($website),
                    'title'         => __("Sidebar's Workshop"),
                    'model'         => $website->name,
                    'icon'          => [
                        'tooltip' => __('Header'),
                        'icon'    => 'fal fa-browser'
                    ],
                    'meta'          => [
                        [
                            'key'      => 'website',
                            'label'    => $website->domain,
                            'leftIcon' => [
                                'icon' => 'fal fa-globe'
                            ]
                        ]
                    ],
                    'actions'       => $this->getActions($website, 'grp.models.website.publish.sidebar')

                ],

                'uploadImageRoute' => [
                    'name'       => 'grp.models.website.menu.images.store',
                    'parameters' => [
                        'website' => $website->id
                    ]
                ],

                'autosaveRoute' => [
                    'name'       => 'grp.models.website.autosave.sidebar',
                    'parameters' => [
                        'website' => $website->id
                    ]
                ],
                'status'        => $isMenuActive ?? true,
                'domain'        => $website->domain,
                'data'          => GetWebsiteWorkshopSidebar::run($website),
                'webBlockTypes' => WebBlockTypesResource::collection(
                    $this->organisation->group->webBlockTypes()->where('fixed', false)->where('scope', 'website')->where('data->component', 'sidebar')->get()
                )
            ]
        );
    }


    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): Website
    {
        $this->parent = $website;
        $this->initialisationFromShop($shop, $request);

        return $website;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): Website
    {
        $this->parent = $website;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $website;
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = ''): array
    {
        $headCrumb = function (array $routeParameters, string $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Workshop')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => 'Sidebar',
                        ],

                    ],
                    'suffix'         => $suffix
                ],
            ];
        };


        return match ($routeName) {
            'grp.org.shops.show.web.websites.workshop.sidebar' => array_merge(
                ShowWebsiteWorkshop::make()->getBreadcrumbs(
                    $routeName,
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.web.websites.workshop',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.web.websites.workshop.sidebar',
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
