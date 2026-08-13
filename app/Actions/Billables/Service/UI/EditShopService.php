<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:21:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Service\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Models\Billables\Service;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class EditShopService extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Service $service): Service
    {
        return $service;
    }

    public function asController(Organisation $organisation, Shop $shop, Service $service, ActionRequest $request): Service
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($service);
    }


    public function htmlResponse(Service $service, ActionRequest $request): Response
    {
        $bluePrints =  [
            [
                'label'  => __('Properties'),
                'title'  => __('Edit service'),
                'fields' => [
                    'code'        => [
                        'type'  => 'input',
                        'label' => __('Code'),
                        'value' => $service->code
                    ],
                    'name'        => [
                        'type'  => 'input',
                        'label' => __('Name'),
                        'value' => $service->name
                    ],
                    'description' => [
                        'type'  => 'textarea',
                        'label' => __('Description'),
                        'value' => $service->description
                    ],
                    'price'       => [
                        'type'  => 'input_number',
                        'label' => __('Price'),
                        'value' => $service->price
                    ],
                    'unit'        => [
                        'type'  => 'input',
                        'label' => __('Unit'),
                        'value' => $service->unit
                    ],
                    'state'       => [
                        'type'     => 'select',
                        'label'    => __('State'),
                        'value'    => $service->state->value,
                        'options'  => Options::forEnum(ServiceStateEnum::class),
                    ],
                ],
            ]
        ];

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Service'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $service,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($service, $request),
                    'next'     => $this->getNext($service, $request),
                ],
                'pageHead'    => [
                    'title'   => $service->name,
                    'icon'    => [
                        'title' => __('Service'),
                        'icon'  => 'fal fa-concierge-bell'
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => $bluePrints,

                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.shop.services.update',
                            'parameters' => [
                                'service' => $service->id
                            ]

                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(Service $service, string $routeName, array $routeParameters): array
    {
        return ShowShopService::make()->getBreadcrumbs(
            service: $service,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }

    public function getPrevious(Service $service, ActionRequest $request): ?array
    {
        $previous = Service::where('shop_id', $this->shop->id)->where('slug', '<', $service->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Service $service, ActionRequest $request): ?array
    {
        $next = Service::where('shop_id', $this->shop->id)->where('slug', '>', $service->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }


    private function getNavigation(?Service $service, string $routeName): ?array
    {
        if (!$service) {
            return null;
        }


        return match ($routeName) {
            'grp.org.shops.show.billables.services.edit' => [
                'label' => $service->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $service->organisation->slug,
                        'shop'         => $service->shop->slug,
                        'service'      => $service->slug
                    ]
                ]
            ],
        };
    }
}
