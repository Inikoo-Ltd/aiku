<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Sept 2024 12:47:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\ShippingZoneSchema\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\ShippingZoneSchema;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditShippingZoneSchema extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(ShippingZoneSchema $shippingZoneSchema): ShippingZoneSchema
    {
        return $shippingZoneSchema;
    }

    public function asController(Organisation $organisation, Shop $shop, ShippingZoneSchema $shippingZoneSchema, ActionRequest $request): ShippingZoneSchema
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($shippingZoneSchema);
    }


    public function htmlResponse(ShippingZoneSchema $shippingZoneSchema, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Shipping Zone Schema'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $shippingZoneSchema,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($shippingZoneSchema, $request),
                    'next'     => $this->getNext($shippingZoneSchema, $request),
                ],
                'pageHead' => [
                    'title'    => $shippingZoneSchema->name,
                    'icon'     => [
                        'title' => __('Trade Unit'),
                        'icon'  => 'fal fa-atom'
                    ],
                    'actions'  => [
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
                    'blueprint' => [
                        [
                            'title'  => __('Edit schema'),
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $shippingZoneSchema->name
                                ],
                            ],
                        ]
                    ],

                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.stock.update',
                            'parameters' => $shippingZoneSchema->id

                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(ShippingZoneSchema $shippingZoneSchema, string $routeName, array $routeParameters): array
    {
        return ShowShippingZoneSchema::make()->getBreadcrumbs(
            shippingZoneSchema: $shippingZoneSchema,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '(' . __('Editing') . ')'
        );
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
            'grp.org.shops.show.billables.shipping.edit' => [
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
