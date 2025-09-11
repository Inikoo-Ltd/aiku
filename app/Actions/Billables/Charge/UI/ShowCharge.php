<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:21:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Charge\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\UI\Catalogue\ChargeTabsEnum;
use App\Enums\UI\Catalogue\ShippingZoneSchemaTabsEnum;
use App\Http\Resources\Catalogue\ShippingZoneSchemaResource;
use App\Models\Billables\Charge;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\ShippingZoneSchema;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowCharge extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Charge $charge): Charge
    {
        return $charge;
    }


    public function asController(Organisation $organisation, Shop $shop, Charge $charge, ActionRequest $request): Charge
    {
        $this->initialisationFromShop($shop, $request)->withTab(ShippingZoneSchemaTabsEnum::values());
        return $this->handle($charge);
    }

    public function htmlResponse(Charge $charge, ActionRequest $request): Response
    {

        return Inertia::render(
            'Org/Catalogue/Charge',
            [
                    'title'       => __('Charge'),
                    'breadcrumbs' => $this->getBreadcrumbs(
                        $charge,
                        $request->route()->getName(),
                        $request->route()->originalParameters()
                    ),
                    'navigation'  => [
                        'previous' => $this->getPrevious($charge, $request),
                        'next'     => $this->getNext($charge, $request),
                    ],
                    'pageHead'    => [
                        'icon'    => [
                            'title' => __('charge'),
                            'icon'  => 'fal fa-charging-station'
                        ],
                        'title'   => $charge->name,
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
                        ]
                    ],
                    'tabs' => [
                        'current'    => $this->tab,
                        'navigation' => ChargeTabsEnum::navigation()

                    ],

                    ChargeTabsEnum::SHOWCASE->value => $this->tab == ChargeTabsEnum::SHOWCASE->value ?
                    fn () => GetChargeShowcase::make($charge)
                    : Inertia::lazy(fn () => GetChargeShowcase::make($charge)),

            ]
        );
    }


    public function jsonResponse(ShippingZoneSchema $shippingZoneSchema): ShippingZoneSchemaResource
    {
        return new ShippingZoneSchemaResource($shippingZoneSchema);
    }

    public function getBreadcrumbs(Charge $charge, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Charge $charge, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Charges')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $charge->slug,
                        ],
                    ],
                    'suffix' => $suffix,

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.billables.charges.show' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $charge,
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

    public function getPrevious(Charge $charge, ActionRequest $request): ?array
    {
        $previous = Charge::where('slug', '<', $charge->slug)->orderBy('slug', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Charge $charge, ActionRequest $request): ?array
    {
        $next = Charge::where('slug', '>', $charge->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Charge $charge, string $routeName): ?array
    {
        if (!$charge) {
            return null;
        }


        return match ($routeName) {
            'grp.org.shops.show.billables.charges.show' => [
                'label' => $charge->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $charge->organisation->slug,
                        'shop'         => $charge->shop->slug,
                        'charge'       => $charge->slug
                    ]
                ]
            ],
        };
    }
}
