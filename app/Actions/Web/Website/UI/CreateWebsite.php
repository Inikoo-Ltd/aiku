<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 18 May 2023 14:27:30 Central European Summer, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateWebsite extends OrgAction
{
    private Fulfilment|Shop $parent;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Fulfilment) {
            return $request->user()->authTo("fulfilment-shop.{$this->parent->id}.edit");
        } elseif ($this->parent instanceof Shop) {
            return $request->user()->authTo("web.{$this->parent->id}.edit");
        }

        return false;
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response|RedirectResponse
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);
        if ($shop->website) {
            return Redirect::route('grp.org.shops.show.web.websites.show', [
                $organisation->slug,
                $shop->slug,
                $shop->website->slug,
            ]);
        }

        return $this->handle($shop, $request);
    }

    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): Response|RedirectResponse
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);
        if ($fulfilment->shop->website) {
            return Redirect::route('grp.org.fulfilments.show.web.websites.show', [
                $organisation->slug,
                $fulfilment->slug,
                $fulfilment->shop->website->slug,
            ]);
        }

        return $this->handle($fulfilment, $request);
    }

    public function handle(Fulfilment|Shop $parent, ActionRequest $request): Response
    {
        $scope = $parent;
        $container = null;
        if (class_basename($scope) == 'Shop') {
            $container = [
                'icon' => ['fal', 'fa-store-alt'],
                'tooltip' => __('Shop'),
                'label' => Str::possessive($scope->name),
            ];
        }

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    routeParameters: $request->route()->originalParameters()
                ),
                'title' => __('New website'),
                'pageHead' => [
                    'title' => __('website'),
                    'container' => $container,
                    'cancelCreate' => [
                        'route' => [
                            'name' => 'shops.show',
                            'parameters' => array_values($request->route()->originalParameters()),
                        ],
                    ],

                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title' => __('domain'),
                            'fields' => [

                                'domain' => [
                                    'type' => 'inputWithAddOn',
                                    'label' => __('domain'),
                                    'leftAddOn' => [
                                        'label' => 'https://',
                                    ],
                                    'placeholder' => 'example.com',
                                    'required' => true,
                                    'value' => '',
                                ],

                            ],
                        ],
                        [
                            'title' => __('ID/name'),
                            'fields' => [

                                'code' => [
                                    'type' => 'input',
                                    'label' => __('code'),
                                    'required' => true,
                                    'placeholder' => 'Enter code',
                                    'value' => '',
                                ],
                                'name' => [
                                    'type' => 'input',
                                    'label' => __('name'),
                                    'placeholder' => 'Enter name',
                                    'required' => true,
                                    'value' => '',
                                ],

                            ],
                        ],

                    ],
                    'route' => match (class_basename($scope)) {
                        'Shop' => [
                            'name' => 'grp.models.shop.website.store',
                            'parameters' => [$parent->id],
                        ],
                        'Fulfilment' => [
                            'name' => 'grp.models.fulfilment.website.store',
                            'parameters' => [$parent->id],
                        ],
                    },

                ],

            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return match (class_basename($this->parent)) {
            'Shop' => array_merge(
                IndexWebsites::make()->getBreadcrumbs(
                    'grp.org.shops.show.web.websites.index',
                    $routeParameters
                ),
                [
                    [
                        'type' => 'creatingModel',
                        'creatingModel' => [
                            'label' => __('creating website'),
                        ],
                    ],
                ]
            ),
            'Fulfilment' => array_merge(
                IndexWebsites::make()->getBreadcrumbs(
                    'grp.org.fulfilments.show.web.websites.index',
                    $routeParameters
                ),
                [
                    [
                        'type' => 'creatingModel',
                        'creatingModel' => [
                            'label' => __('creating website'),
                        ],
                    ],
                ]
            ),
        };
    }
}
