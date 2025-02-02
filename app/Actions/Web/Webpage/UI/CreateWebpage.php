<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:35:41 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\InertiaAction;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateWebpage extends InertiaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('websites.edit');
    }


    public function asController(Website $website, ActionRequest $request): Webpage
    {
        $this->initialisation($request);
        return $website->storefront;
    }

    public function inWebsiteInWebpage(Website $website, Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisation($request);

        return $webpage;
    }

    public function htmlResponse(Webpage $parent, ActionRequest $request): Response
    {


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

                                'url' => [
                                    'type'      => 'inputWithAddOn',
                                    'label'     => __('url'),
                                    'leftAddOn' => [
                                        'label' => 'https://'.$parent->website->domain.'/'
                                    ],
                                    'value'     => '',
                                    'required'  => true,
                                ],
                            ]
                        ]


                    ],
                    'route'     => [
                        'name'       => 'org.models.webpage.store',
                        'parameters' => [$parent->id]
                    ],


                ],

            ]
        );
    }


    public function getBreadcrumbs($routeName, $routeParameters): array
    {

        return match ($routeName) {
            'org.websites.show.webpages.show.webpages.create' =>
            array_merge(
                ShowWebpage::make()->getBreadcrumbs($routeParameters),
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
