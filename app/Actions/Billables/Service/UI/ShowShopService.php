<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:21:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Service\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\Fulfilment\UI\Catalogue\Services\GetFulfilmentServiceShowcase;
use App\Enums\UI\Fulfilment\FulfilmentServiceTabsEnum;
use App\Http\Resources\Fulfilment\ServicesResource;
use App\Models\Billables\Service;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowShopService extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Service $service): Service
    {
        return $service;
    }


    public function asController(Organisation $organisation, Shop $shop, Service $service, ActionRequest $request): Service
    {
        $this->initialisationFromShop($shop, $request)->withTab(FulfilmentServiceTabsEnum::values());
        return $this->handle($service);
    }

    public function htmlResponse(Service $service, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Billables/Service',
            [
                    'title'       => __('Service') . ' - ' . $service->name,
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
                        'icon'    => [
                            'title' => __('Service'),
                            'icon'  => 'fal fa-concierge-bell'
                        ],
                        'model'  => __('Service'),
                        'title'   => $service->name,
                        'actions' => [
                            $this->canEdit ? [
                                'type'  => 'button',
                                'style' => 'edit',
                                'route' => [
                                    'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                    'parameters' => array_values($request->route()->originalParameters())
                                ]
                            ] : false,
                        ]
                    ],
                    'tabs' => [
                        'current'    => $this->tab,
                        'navigation' => FulfilmentServiceTabsEnum::navigation()

                    ],

                    FulfilmentServiceTabsEnum::SHOWCASE->value => $this->tab == FulfilmentServiceTabsEnum::SHOWCASE->value ?
                        fn () => GetFulfilmentServiceShowcase::run($service)
                        : Inertia::optional(fn () => GetFulfilmentServiceShowcase::run($service)),
            ]
        );
    }


    public function jsonResponse(Service $service): ServicesResource
    {
        return new ServicesResource($service);
    }

    public function getBreadcrumbs(Service $service, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Service $service, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Services')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $service->code,
                        ],
                    ],
                    'suffix' => $suffix,

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.billables.services.show' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $service,
                    [
                        'index' => [
                            'name'       => preg_replace('/show$/', 'index', $routeName),
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
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
            'grp.org.shops.show.billables.services.show' => [
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
