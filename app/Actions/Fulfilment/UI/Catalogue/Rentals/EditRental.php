<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Dec 2024 23:47:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\UI\Catalogue\Rentals;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopEditAuthorisation;
use App\Enums\Billables\Rental\RentalUnitEnum;
use App\Models\Billables\Rental;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class EditRental extends OrgAction
{
    use WithFulfilmentShopEditAuthorisation;

    public function handle(Rental $rental): Rental
    {
        return $rental;
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, Rental $rental, ActionRequest $request): Rental
    {

        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($rental);
    }

    /**
     * @throws \Exception
     */
    public function htmlResponse(Rental $rental, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('rental'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $rental,
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($rental, $request),
                    'next'     => $this->getNext($rental, $request),
                ],
                'pageHead'    => [
                    'title'   => $rental->code,
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-cube'],
                            'title' => __('Rental')
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
                            'title'  => __('id'),
                            'fields' => [
                                'code'        => [
                                    'type'     => 'input',
                                    'label'    => __('code'),
                                    'value'    => $rental->code,
                                    'readonly' => true
                                ],
                                'name'        => [
                                    'type'  => 'input',
                                    'label' => __('label'),
                                    'value' => $rental->name,
                                ],
                                'description' => [
                                    'type'  => 'input',
                                    'label' => __('description'),
                                    'value' => $rental->description
                                ],
                                'unit'        => [
                                    'type'    => 'select',
                                    'label'   => __('unit'),
                                    'value'   => $rental->unit,
                                    'options' => Options::forEnum(RentalUnitEnum::class)
                                ],
                                'units'       => [
                                    'type'  => 'input',
                                    'label' => __('units'),
                                    'value' => $rental->units,
                                ],
                                'price'       => [
                                    'type'     => 'input',
                                    'label'    => __('price'),
                                    'required' => true,
                                    'value'    => $rental->price
                                ],
                            ]
                        ]

                    ],
                    // Make sure to change the FulfilmentUITest after fixing this
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.rentals.update',
                            'parameters' => [
                                'rental' => $rental->id
                            ]

                        ],
                    ]
                ]

            ]
        );
    }

    public function getBreadcrumbs(Rental $rental, array $routeParameters): array
    {
        return ShowRental::make()->getBreadcrumbs(
            $rental,
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }

    public function getPrevious(Rental $rental, ActionRequest $request): ?array
    {
        $previous = Rental::where('slug', '<', $rental->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Rental $rental, ActionRequest $request): ?array
    {
        $next = Rental::where('slug', '>', $rental->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Rental $rental, string $routeName): ?array
    {
        if (!$rental) {
            return null;
        }

        return match ($routeName) {
            'shops.products.edit' => [
                'label' => $rental->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'product' => $rental->slug
                    ]

                ]
            ],
            'shops.show.products.edit' => [
                'label' => $rental->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'shop'    => $rental->shop->slug,
                        'product' => $rental->slug
                    ]

                ]
            ],
            default => null,
        };
    }
}
