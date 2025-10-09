<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:22:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\UI\Catalogue\Services;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\UI\Catalogue\ShowFulfilmentCatalogueDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Enums\UI\Catalogue\ServiceTabsEnum;
use App\Enums\UI\Fulfilment\FulfilmentServiceTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Billables\Service;
use App\Models\Catalogue\Asset;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowFulfilmentService extends OrgAction
{
    use WithFulfilmentShopAuthorisation;

    public function handle(Service $service): Service
    {
        return $service;
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, Service $service, ActionRequest $request): Service
    {
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(ProductTabsEnum::values());
        return $this->handle($service);
    }

    public function htmlResponse(Service $service, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Fulfilment/Service',
            [
                'title'       => __('Service'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($service, $request),
                    'next'     => $this->getNext($service, $request),
                ],
                'pageHead'    => [
                    'model'   => __('service'),
                    'title'   => $service->code,
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-concierge-bell'],
                            'title' => __('Service')
                        ],
                    'iconRight' => ServiceStateEnum::stateIcon()[$service->state->value],
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,

                    ]
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => FulfilmentServiceTabsEnum::navigation()
                ],


                FulfilmentServiceTabsEnum::SHOWCASE->value => $this->tab == ServiceTabsEnum::SHOWCASE->value ?
                    fn () => GetFulfilmentServiceShowcase::run($service)
                    : Inertia::lazy(fn () => GetFulfilmentServiceShowcase::run($service)),
            ]
        );
    }

    public function jsonResponse(Asset $product): ProductsResource
    {
        return new ProductsResource($product);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
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
                            'label' => $service->slug,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        $service = Service::where('slug', $routeParameters['service'])->first();

        return match ($routeName) {
            'grp.org.fulfilments.show.catalogue.show' =>
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $service,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.catalogue.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.catalogue.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.fulfilments.show.catalogue.services.show' =>
            array_merge(
                (new ShowFulfilmentCatalogueDashboard())->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $service,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.catalogue.services.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.catalogue.services.show',
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
            'grp.org.fulfilments.show.catalogue.services.show' => [
                'label' => $service->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $service->organisation->slug,
                        'fulfilment'   => $service->asset->shop->slug,
                        'service'      => $service->slug
                    ],
                ],
            ],
            default => null,
        };
    }
}
