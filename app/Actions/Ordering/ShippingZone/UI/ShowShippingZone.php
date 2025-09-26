<?php

/*
 * author Arya Permana - Kirin
 * created on 18-10-2024-15h-07m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\ShippingZone\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\UI\Catalogue\ShippingZoneTabsEnum;
use App\Http\Resources\Catalogue\ShippingZoneResource;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\ShippingZone;
use App\Models\Ordering\ShippingZoneSchema;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowShippingZone extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(ShippingZone $shippingZone): ShippingZone
    {
        return $shippingZone;
    }


    public function asController(Organisation $organisation, Shop $shop, ShippingZoneSchema $shippingZoneSchema, ShippingZone $shippingZone, ActionRequest $request): ShippingZone
    {
        $this->initialisationFromShop($shop, $request)->withTab(ShippingZoneTabsEnum::values());
        return $this->handle($shippingZone);
    }

    public function htmlResponse(ShippingZone $shippingZone, ActionRequest $request): Response
    {

        return Inertia::render(
            'Org/Catalogue/ShippingZoneSchema',
            [
                    'title'       => __('Shipping Zone'),
                    'breadcrumbs' => $this->getBreadcrumbs(
                        $shippingZone,
                        $request->route()->getName(),
                        $request->route()->originalParameters()
                    ),
                    'navigation'  => [
                        'previous' => $this->getPrevious($shippingZone, $request),
                        'next'     => $this->getNext($shippingZone, $request),
                    ],
                    'pageHead'    => [
                        'icon'    => [
                            'title' => __('Trade unit'),
                            'icon'  => 'fal fa-atom'
                        ],
                        'title'   => $shippingZone->name,
                        'actions' => [
                            $this->canEdit ? [
                                'type'  => 'button',
                                'style' => 'edit',
                                'route' => [
                                    'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                    'parameters' => array_values($request->route()->originalParameters())
                                ]
                            ] : false,
                            // $this->canDelete ? [
                            //     'type'  => 'button',
                            //     'style' => 'delete',
                            //     'route' => [
                            //         'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.show.stocks.remove',
                            //         'parameters' => array_values($request->route()->originalParameters())
                            //     ]

                            // ] : false
                                ],
                    ],
                    'tabs' => [
                        'current'    => $this->tab,
                        'navigation' => ShippingZoneTabsEnum::navigation()

                    ],
            ]
        );
    }


    public function jsonResponse(ShippingZone $shippingZone): ShippingZoneResource
    {
        return new ShippingZoneResource($shippingZone);
    }

    public function getBreadcrumbs(ShippingZone $shippingZone, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (ShippingZone $shippingZone, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Shipping zone')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $shippingZone->slug,
                        ],
                    ],
                    'suffix' => $suffix,

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.billables.shipping.show.shipping-zone.show' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $shippingZone,
                    [
                        'index' => [
                            'name'       => "grp.org.shops.show.billables.shipping.show",
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

    public function getPrevious(ShippingZone $shippingZone, ActionRequest $request): ?array
    {
        $previous = ShippingZone::where('slug', '<', $shippingZone->slug)->orderBy('slug', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(ShippingZone $shippingZone, ActionRequest $request): ?array
    {
        $next = ShippingZone::where('slug', '>', $shippingZone->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?ShippingZone $shippingZone, string $routeName): ?array
    {
        // dd($shippingZone->schema);
        if (!$shippingZone) {
            return null;
        }


        return match ($routeName) {
            'grp.org.shops.show.billables.shipping.show.shipping-zone.show' => [
                'label' => $shippingZone->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'       => $shippingZone->organisation->slug,
                        'shop'               => $shippingZone->shop->slug,
                        'shippingZoneSchema' => $shippingZone->schema->slug,
                        'shippingZone'       => $shippingZone->slug
                    ]
                ]
            ],
        };
    }
}
