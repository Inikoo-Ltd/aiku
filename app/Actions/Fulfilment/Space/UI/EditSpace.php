<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Space\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopEditAuthorisation;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Space;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditSpace extends OrgAction
{
    use WithFulfilmentShopEditAuthorisation;

    public function handle(Space $space, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Edit space'),
                'pageHead'    => [
                    'title'   => __('Edit space'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-parking'],
                        'title' => __('Space')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Exit edit'),
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' =>
                        [
                            [
                                'title'  => __('information'),
                                'fields' => [
                                    'reference' => [
                                        'type'     => 'input',
                                        'required' => true,
                                        'value' => $space->reference,
                                        'label'    => __('Reference')
                                    ],

//                                    'start_at'        => [
//                                        'type'     => 'date',
//                                        'required' => true,
//                                        'value' => $space->start_at,
//                                        'label'    => __('start rent at')
//                                    ],
//                                    'end_at'          => [
//                                        'type'  => 'date',
//                                        'value' => $space->end_at,
//                                        'label' => __('end rent at')
//                                    ],
//                                    'rental_id'       => [
//                                        'type'     => 'select',
//                                        'required' => true,
//                                        'label'    => __('rental'),
//                                        'value' => $space->rental_id,
//                                        'options'  => Options::forModels(Rental::where('type', RentalTypeEnum::SPACE->value))
//                                    ],
//                                    'exclude_weekend' => [
//                                        'type'     => 'toggle',
//                                        'required' => true,
//                                        'value'    => $space->exclude_weekend,
//                                        'label'    => __('exclude weekend')
//                                    ],
                                ]
                            ]
                        ],
                        'args' => [
                            'updateRoute'     => [
                                'name'       => 'grp.models.fulfilment_customer_space.update',
                                'parameters' => [
                                    'fulfilmentCustomer' => $space->fulfilment_customer_id,
                                    'space' => $space->id
                                ]
                            ]
                        ]
                ]
            ]
        );
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, Space $space, ActionRequest $request): Response
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($space, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowSpace::make()->getBreadcrumbs(
                routeName: $routeName,
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Editing space'),
                    ]
                ]
            ]
        );
    }
}
