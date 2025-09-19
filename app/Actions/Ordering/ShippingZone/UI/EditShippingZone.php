<?php

/*
 * author Arya Permana - Kirin
 * created on 18-10-2024-15h-44m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\ShippingZone\UI;

use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\UI\Catalogue\ShippingZoneSchemaTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\ShippingZone;
use App\Models\Ordering\ShippingZoneSchema;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditShippingZone extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(ShippingZone $shippingZone): ShippingZone
    {
        return $shippingZone;
    }

    public function asController(Organisation $organisation, Shop $shop, ShippingZoneSchema $shippingZoneSchema, ShippingZone $shippingZone, ActionRequest $request): ShippingZone
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($shippingZone);
    }


    public function htmlResponse(ShippingZone $shippingZone, ActionRequest $request): Response
    {

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Shipping Zone Schema'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $shippingZone,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($shippingZone, $request),
                    'next'     => $this->getNext($shippingZone, $request),
                ],
                'pageHead' => [
                    'title'    => $shippingZone->name,
                    'icon'     => [
                        'title' => __('Shipping Zone'),
                        'icon'  => 'fal fa-atom'
                    ],
                    'actions'  => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                // 'name'       => preg_replace('/edit$/', 'index', $request->route()->getName()),
                                'name'  => 'grp.org.shops.show.billables.shipping.show',
                                'parameters' => [
                                    'organisation' => $shippingZone->organisation->slug,
                                    'shop' => $shippingZone->shop->slug,
                                    'shippingZoneSchema' => $shippingZone->schema->slug,
                                    'tab' => ShippingZoneSchemaTabsEnum::ZONES->value
                                ]
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('edit schema'),
                            'fields' => [
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $shippingZone->name
                                ],
                                'territories' => [
                                    'type'  => 'teritory_zone',
                                    'label' => __('territory'),
                                    'value' => $shippingZone->territories,
                                    'country_list' => GetCountriesOptions::run(),
                                ],
                                'price' => [
                                    'type'  => 'pricing_zone',
                                    'label' => __('price'),
                                    'value' => $shippingZone->price
                                ],
                            ],
                        ]
                    ],

                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.shipping_zone.update',
                            'parameters' => $shippingZone->id
                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(ShippingZone $shippingZone, string $routeName, array $routeParameters): array
    {
        return ShowShippingZone::make()->getBreadcrumbs(
            shippingZone: $shippingZone,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '(' . __('Editing') . ')'
        );
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
        if (!$shippingZone) {
            return null;
        }


        return match ($routeName) {
            'grp.org.shops.show.billables.shipping.show.shipping-zone.edit' => [
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
