<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Dec 2024 23:47:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\UI\Catalogue\Rentals;

use App\Actions\OrgAction;
use App\Enums\Billables\Rental\RentalStateEnum;
use App\Enums\Billables\Rental\RentalUnitEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateRental extends OrgAction
{
    /**
     * @throws Exception
     */
    public function handle(Fulfilment $fulfilment, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('New rental'),
                'pageHead'    => [
                    'title' => __('New rental')
                ],
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('name'),
                                'fields' => [
                                    'code'  => [
                                        'type'     => 'input',
                                        'label'    => __('code'),
                                        'required' => true
                                    ],
                                    'name'  => [
                                        'type'     => 'input',
                                        'label'    => __('name'),
                                        'required' => true
                                    ],
                                    'price' => [
                                        'type'     => 'input',
                                        'label'    => __('price'),
                                        'required' => true
                                    ],
                                    'unit'  => [
                                        'type'     => 'select',
                                        'label'    => __('unit'),
                                        'required' => true,
                                        'options'  => Options::forEnum(RentalUnitEnum::class)
                                    ],
                                    'state' => [
                                        'type'     => 'select',
                                        'label'    => __('state'),
                                        'required' => true,
                                        'options'  => Options::forEnum(RentalStateEnum::class)
                                    ]
                                ]
                            ]
                        ],
                    'route'      => [
                        'name'       => 'grp.models.org.fulfilment.rentals.store',
                        'parameters' => [
                            'organisation' => $fulfilment->organisation_id,
                            'fulfilment'   => $fulfilment->id,
                        ]
                    ]
                ],

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    /**
     * @throws Exception
     */
    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): Response
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment, $request);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexFulfilmentRentals::make()->getBreadcrumbs(
                'grp.org.fulfilments.show.catalogue.rentals.index',
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating rental'),
                    ]
                ]
            ]
        );
    }

}
