<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 25 Apr 2024 16:56:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\Website\GetWebsiteWorkshopHeader;
use App\Actions\Web\Website\UI\ShowWebsiteWorkshop;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Arr;

class ShowHeaderWorkshop extends OrgAction
{
    use WithHeaderSubNavigation;
    use WithWebEditAuthorisation;
    use WithWebsiteWorkshop;

    private Webpage|Website $parent;


    public function handle(Website $website): Website
    {
        return $website;
    }

    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        $headerLayout   = Arr::get($website->published_layout, 'header');
        $isHeaderActive = Arr::get($headerLayout, 'status');

        return Inertia::render(
            'Org/Web/Workshop/Header/HeaderWorkshop',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __("Website Header's Workshop"),
                'pageHead'    => [
                    'subNavigation' => $this->getHeaderSubNavigation($website),
                    'title'         => __("Header's Workshop"),
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
                    'actions'       => $this->getActions($website, 'grp.models.website.publish.header')

                ],

                'uploadImageRoute' => [
                    'name'       => 'grp.models.website.header.images.store',
                    'parameters' => [
                        'website' => $website->id
                    ]
                ],

                'autosaveRoute' => [
                    'name'       => 'grp.models.website.autosave.header',
                    'parameters' => [
                        'website' => $website->id
                    ]
                ],

                'route_list'      => [
                    'upload_image'         => [
                        'name'       => 'grp.models.website.header.images.store',
                        'parameters' => [
                            'website' => $website->id
                        ]
                    ],
                    'uploaded_images_list' => [
                        'name'       => 'grp.gallery.uploaded-images.index',
                        'parameters' => []
                    ],
                    'stock_images_list'    => [
                        'name'       => 'grp.gallery.stock-images.index',
                        'parameters' => []
                    ],
                ],
                'state'           => $isHeaderActive ?? true,
                'domain'          => $website->domain,
                'data'            => GetWebsiteWorkshopHeader::run($website),
                'web_block_types' => WebBlockTypesResource::collection(
                    $this->organisation->group->webBlockTypes()->where('fixed', false)->where('scope', 'website')->orderBy('id')->get()
                )->toArray($request)
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
                            'label' => 'Header',
                        ],

                    ],
                    'suffix'         => $suffix
                ],
            ];
        };


        return match ($routeName) {
            'grp.org.shops.show.web.websites.workshop.header' => array_merge(
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
                            'name'       => 'grp.org.shops.show.web.websites.workshop.header',
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
