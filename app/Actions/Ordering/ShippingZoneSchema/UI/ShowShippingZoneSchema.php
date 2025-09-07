<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Sept 2024 12:47:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\ShippingZoneSchema\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Ordering\ShippingZone\UI\IndexShippingZones;
use App\Actions\Ordering\ShippingZoneSchema\WithShippingZoneSchemaSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\UI\Catalogue\ShippingZoneSchemaTabsEnum;
use App\Http\Resources\Catalogue\ShippingZoneSchemaResource;
use App\Http\Resources\Catalogue\ShippingZonesResource;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\ShippingZoneSchema;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowShippingZoneSchema extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithShippingZoneSchemaSubNavigation;

    public function handle(ShippingZoneSchema $shippingZoneSchema): ShippingZoneSchema
    {
        return $shippingZoneSchema;
    }


    public function asController(Organisation $organisation, Shop $shop, ShippingZoneSchema $shippingZoneSchema, ActionRequest $request): ShippingZoneSchema
    {
        $this->initialisationFromShop($shop, $request)->withTab(ShippingZoneSchemaTabsEnum::values());

        return $this->handle($shippingZoneSchema);
    }

    public function htmlResponse(ShippingZoneSchema $shippingZoneSchema, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Catalogue/ShippingZoneSchema',
            [
                'title'                                  => __('Shipping Zone Schema'),
                'breadcrumbs'                            => $this->getBreadcrumbs(
                    $shippingZoneSchema,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                             => [
                    'previous' => $this->getPrevious($shippingZoneSchema, $request),
                    'next'     => $this->getNext($shippingZoneSchema, $request),
                ],
                'pageHead'                               => [
                    'icon'          => [
                        'title' => __('trade unit'),
                        'icon'  => 'fal fa-atom'
                    ],
                    'title'         => $shippingZoneSchema->name,
                    'actions'       => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ],
                    'subNavigation' => $this->getShippingZoneSchemaSubNavigation($shippingZoneSchema->shop),
                ],
                'tabs'                                   => [
                    'current'    => $this->tab,
                    'navigation' => ShippingZoneSchemaTabsEnum::navigation()

                ],
                ShippingZoneSchemaTabsEnum::ZONES->value => $this->tab == ShippingZoneSchemaTabsEnum::ZONES->value ?
                    fn () => ShippingZonesResource::collection(IndexShippingZones::run($shippingZoneSchema, ShippingZoneSchemaTabsEnum::ZONES->value))
                    : Inertia::lazy(fn () => ShippingZonesResource::collection(IndexShippingZones::run($shippingZoneSchema)))
            ]
        )->table(IndexShippingZones::make()->tableStructure(parent: $shippingZoneSchema, prefix: ShippingZoneSchemaTabsEnum::ZONES->value));
    }


    public function jsonResponse(ShippingZoneSchema $shippingZoneSchema): ShippingZoneSchemaResource
    {
        return new ShippingZoneSchemaResource($shippingZoneSchema);
    }

    public function getBreadcrumbs(ShippingZoneSchema $shippingZoneSchema, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (ShippingZoneSchema $shippingZoneSchema, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Shippings')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $shippingZoneSchema->slug,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.billables.shipping.show' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $shippingZoneSchema,
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

    public function getPrevious(ShippingZoneSchema $shippingZoneSchema, ActionRequest $request): ?array
    {
        $previous = ShippingZoneSchema::where('slug', '<', $shippingZoneSchema->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(ShippingZoneSchema $shippingZoneSchema, ActionRequest $request): ?array
    {
        $next = ShippingZoneSchema::where('slug', '>', $shippingZoneSchema->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?ShippingZoneSchema $shippingZoneSchema, string $routeName): ?array
    {
        if (!$shippingZoneSchema) {
            return null;
        }


        return match ($routeName) {
            'grp.org.shops.show.billables.shipping.show' => [
                'label' => $shippingZoneSchema->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'       => $shippingZoneSchema->organisation->slug,
                        'shop'               => $shippingZoneSchema->shop->slug,
                        'shippingZoneSchema' => $shippingZoneSchema->slug
                    ]
                ]
            ],
        };
    }
}
