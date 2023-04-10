<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 12 Oct 2022 17:04:31 Central European Summer, Benalmádena, Malaga, Spain
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\InertiaAction;
use App\Actions\Marketing\Shop\ShowShop;
use App\Actions\UI\Dashboard\Dashboard;
use App\Actions\UI\WithInertia;
use App\Http\Resources\Marketing\WebsiteResource;
use App\Models\Marketing\Shop;
use App\Models\Web\Website;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowWebsite extends InertiaAction
{
    use AsAction;
    use WithInertia;


    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasPermissionTo("shops.websites.view");
    }

    public function asController(Website $website): Website
    {
        return $website;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Shop $shop, Website $website, Request $request): Website
    {
        return $website;
    }

    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        $this->validateAttributes();


        return Inertia::render(
            'Web/Website',
            [
                'title'       => __('Website'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->parameters
                ),
                'pageHead'    => [
                    'title' => $website->name,


                ],
                'Website' => new WebsiteResource($website),
            ]
        );
    }


    public function jsonResponse(Website $website): WebsiteResource
    {
        return new WebsiteResource($website);
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
                            'label' => __('websites')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $website->name,
                        ],

                    ],
                    'simple'=> [
                        'route' => $routeParameters['model'],
                        'label' => $website->name
                    ],


                    'suffix'=> $suffix

                ],
            ];
        };




        return match ($routeName) {
            'websites.show',
            'websites.edit' =>

            array_merge(
                Dashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    'modelWithIndex',
                    $routeParameters['website'],
                    [
                        'index' => [
                            'name'       => 'websites.index',
                            'parameters' => []
                        ],
                        'model' => [
                            'name'       => 'websites.show',
                            'parameters' => [$routeParameters['website']->slug]
                        ]
                    ],
                    $suffix
                ),
            ),


            'shops.show.websites.show',
            'shops.show.websites.edit'
            => array_merge(
                (new ShowShop())->getBreadcrumbs($routeParameters['shop']),
                $headCrumb(
                    'simple',
                    $routeParameters['website'],
                    [
                        'index' => [
                            'name'       => 'shops.show.websites.index',
                            'parameters' => [
                                $routeParameters['shop']->slug,
                            ]
                        ],
                        'model' => [
                            'name'       => 'shops.show.websites.show',
                            'parameters' => [
                                $routeParameters['shop']->slug,
                                $routeParameters['website']->slug
                            ]
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }
}
