<?php

/*
 * Author: Artha <artha@iku.io>
 * Created: Mon, 19 May 2026 10:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Artha
 */

namespace App\Actions\Billables\ShippingZone\UI;

use App\Actions\Billables\ShippingZoneSchema\UI\ShowShippingZoneSchema;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Models\Billables\ShippingZoneSchema;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateShippingZone extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(ShippingZoneSchema $shippingZoneSchema, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $shippingZoneSchema,
                    $request->route()->originalParameters()
                ),
                'title'    => __('New Shipping Zone'),
                'pageHead' => [
                    'title'   => __('New Shipping Zone'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-shipping-fast'],
                        'title' => __('Shipping Zone'),
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => $shippingZoneSchema->is_current ? 'grp.org.shops.show.billables.shipping.current.show' : 'grp.org.shops.show.billables.shipping.discount.show',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('Details'),
                            'fields' => [
                                'code' => [
                                    'type'     => 'input',
                                    'label'    => __('Code'),
                                    'required' => true,
                                ],
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('Name'),
                                    'required' => true,
                                ],
                                /* 'position' => [
                                    'type'     => 'input',
                                    'inputType' => 'number',
                                    'label'    => __('Position'),
                                    'required' => true,
                                    'value'    => null,
                                ], */
                                'status' => [
                                    'type'  => 'toggle',
                                    'label' => __('Status'),
                                    'value' => true,
                                ],
                                'is_failover' => [
                                    'type'  => 'toggle',
                                    'label' => __('Failover zone'),
                                    'value' => false,
                                ],
                            ],
                        ],
                        [
                            'title'  => __('Territories & Pricing'),
                            'fields' => [
                                'territories' => [
                                    'type'         => 'territory_zone',
                                    'label'        => __('Territories'),
                                    'value'        => [],
                                    'country_list' => GetCountriesOptions::run(),
                                ],
                                'price' => [
                                    'type'     => 'pricing_zone',
                                    'label'    => __('Price'),
                                    'required' => true,
                                    'currency' => $shippingZoneSchema->shop->currency,
                                    'value'    => [
                                        'type'  => 'Step Order Items Net Amount',
                                        'steps' => [
                                            [
                                                'from'  => 0,
                                                'to'    => 'INF',
                                                'price' => 0,
                                            ],
                                        ],
                                    ],
                            ],
                            ],
                        ],
                    ],
                    'route' => [
                        'name'       => 'grp.models.shipping_zone.create',
                        'parameters' => [$shippingZoneSchema->id],
                    ],
                ],
            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, ShippingZoneSchema $shippingZoneSchema, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shippingZoneSchema, $request);
    }

    public function getBreadcrumbs(ShippingZoneSchema $shippingZoneSchema, array $routeParameters): array
    {
        return array_merge(
            ShowShippingZoneSchema::make()->getBreadcrumbs(
                shippingZoneSchema: $shippingZoneSchema,
                routeName: $shippingZoneSchema->is_current ? 'grp.org.shops.show.billables.shipping.current.show' : 'grp.org.shops.show.billables.shipping.discount.show',
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating Shipping Zone'),
                    ],
                ],
            ]
        );
    }
}
