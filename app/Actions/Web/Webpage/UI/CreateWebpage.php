<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:35:41 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Enums\Web\Webpage\WebpageSeoStructureTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateWebpage extends OrgAction
{
    use WithWebAuthorisation;

    protected Fulfilment|Website|Webpage $parent;



    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): Website
    {
        $this->scope = $shop;
        $this->parent = $website;
        $this->initialisationFromShop($shop, $request);

        return $website;
    }



    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): Website
    {
        $this->scope  = $fulfilment;
        $this->parent = $website;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $website;
    }

    public function htmlResponse(Webpage|Website $parent, ActionRequest $request): Response
    {
        $route = [];
        if ($this->scope instanceof Fulfilment) {
            $route = [
                'name'       => 'grp.models.fulfilment.webpage.store',
                'parameters' => [$this->scope->id, $parent->id]
            ];
        } else {
            $route = [
                'name'       => 'grp.models.shop.webpage.store',
                'parameters' => [$this->scope->id, $parent->id]
            ];
        }
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('new webpage'),
                'pageHead'    => [
                    'title'   => __('new webpage'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'route' =>
                                match ($request->route()->getName()) {
                                    'org.websites.show.webpages.show.webpages.create' => [
                                        'name'       => 'org.websites.show.webpages.show' ,
                                        'parameters' => array_values($request->route()->originalParameters())
                                    ],
                                    'grp.org.shops.show.web.blogs.create' => [
                                        'name'       => 'grp.org.shops.show.web.blogs.index' ,
                                        'parameters' => array_values($request->route()->originalParameters())
                                    ],
                                    default => [
                                        'name'       => preg_replace('/create$/', 'index', $request->route()->getName()),
                                        'parameters' => array_values($request->route()->originalParameters())
                                    ]
                                }


                        ]
                    ]


                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('Id'),
                            'icon'   => ['fal', 'fa-fingerprint'],
                            'fields' => [
                                'code' => [
                                    'type'     => 'input',
                                    'label'    => __('code'),
                                    'value'    => '',
                                    'required' => true,
                                ],
                                'title' => [
                                    'type'     => 'input',
                                    'label'    => __('title'),
                                    'value'    => '',
                                    'required' => true,
                                ],
                                'url' => [
                                    'type'      => 'inputWithAddOn',
                                    'label'     => __('URL'),
                                    'label_no_capitalize' => true,
                                    'leftAddOn' => [
                                        'label' => 'https://' . ($parent instanceof Webpage ? $parent->website->domain : $parent->domain) . '/'
                                    ],
                                    'value'     => '',
                                    'required'  => true,
                                ],
                                'seo_structure_type' => [
                                    'type'     => 'select',
                                    'required'  => true,
                                    'label'    => __('seo structure type'),
                                    'options'  => Options::forEnum(WebpageSeoStructureTypeEnum::class),
                                    'value'    => null,
                                    'required' => false,
                                ],

                            ]
                        ]
                    ],
                    'route'     => $route,

                ],

            ]
        );
    }


    public function getBreadcrumbs($routeName, $routeParameters): array
    {

        return match ($routeName) {
            'org.websites.show.webpages.show.webpages.create' =>
            array_merge(
                ShowWebpage::make()->getBreadcrumbs('org.websites.show.webpages.show.webpages.show', $routeParameters),
                [
                    [
                        'type'          => 'creatingModel',
                        'creatingModel' => [
                            'label' => __("webpage"),
                        ]
                    ]
                ]
            ),
            'grp.org.shops.show.web.blogs.create' =>
            array_merge(
                IndexBlogWebpages::make()->getBreadcrumbs('grp.org.shops.show.web.blogs.index', $routeParameters),
                [
                    [
                        'type'          => 'creatingModel',
                        'creatingModel' => [
                            'label' => __("webpage"),
                        ]
                    ]
                ]
            ),
            'org.websites.show.webpages.create' =>
            array_merge(
                IndexWebpages::make()->getBreadcrumbs($routeName, $routeParameters),
                [
                    [
                        'type'          => 'creatingModel',
                        'creatingModel' => [
                            'label' => __("webpage"),
                        ]
                    ]
                ]
            ),
            default => []
        };
    }
}
