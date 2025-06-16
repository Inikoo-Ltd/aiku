<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:22:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\UI\Catalogue\Services;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopEditAuthorisation;
use App\Enums\Billables\Service\ServiceEditTypeEnum;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Models\Billables\Service;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditFulfilmentService extends OrgAction
{
    use WithFulfilmentShopEditAuthorisation;

    public function handle(Service $service): Service
    {
        return $service;
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, Service $service, ActionRequest $request): Service
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($service);
    }

    /**
     * @throws \Exception
     */
    public function htmlResponse(Service $service, ActionRequest $request): Response
    {
        if ($service->edit_type == ServiceEditTypeEnum::QUANTITY) {
            $fixedPrice = true;
            $disableNet = false;
        } else {
            $fixedPrice = false;
            $disableNet = true;
        }

        if (!$service->status || $service->state == ServiceStateEnum::DISCONTINUED) {
            $active = false;
        } else {
            $active = true;
        }

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('service'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($service, $request),
                    'next'     => $this->getNext($service, $request),
                ],
                'pageHead'    => [
                    'title'   => $service->code,
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-cube'],
                            'title' => __('product')
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
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('Edit Service'),
                            'fields' => [
                                'in_public'   => [
                                    'type'  => 'toggle',
                                    'label' => __('public'),
                                    'value' => $service->is_public
                                ],
                                'active'      => [
                                    'type'  => 'toggle',
                                    'label' => __('active'),
                                    'value' => $active
                                ],
                                'code'        => [
                                    'type'     => 'input',
                                    'label'    => __('code'),
                                    'value'    => $service->code,

                                ],
                                'name'        => [
                                    'type'     => 'input',
                                    'label'    => __('label'),
                                    'value'    => $service->name,

                                ],
                                'description' => [
                                    'type'  => 'input',
                                    'label' => __('description'),
                                    'value' => $service->description
                                ],
                                'unit'        => [
                                    'type'     => 'input',
                                    'label'    => __('unit'),
                                    'value'    => $service->unit,
                                ],
                                'fixed_price' => [
                                    'type'  => 'toggle',
                                    'label' => __('fixed price'),
                                    'value' => $fixedPrice
                                ],
                                'price'       => [
                                    'type'   => 'input',
                                    'label'  => __('price'),
                                    'value'  => $service->price,
                                    'hidden' => $disableNet
                                ],

                            ]
                        ]

                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.fulfilment.services.update',
                            'parameters' => [
                                'service' => $service->id
                            ]

                        ],
                    ]
                ]

            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowFulfilmentService::make()->getBreadcrumbs(
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
            'grp.org.fulfilments.show.catalogue.services.edit' => [
                'label' => $service->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'fulfilment'   => $this->fulfilment->slug,
                        'service'      => $service->slug
                    ]

                ]
            ],
            default => null,
        };
    }
}
