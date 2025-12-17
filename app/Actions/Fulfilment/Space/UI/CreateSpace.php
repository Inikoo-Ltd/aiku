<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Space\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopEditAuthorisation;
use App\Enums\Billables\Rental\RentalStateEnum;
use App\Enums\Billables\Rental\RentalTypeEnum;
use App\Models\Billables\Rental;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateSpace extends OrgAction
{
    use WithFulfilmentShopEditAuthorisation;

    public function handle(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('New space'),
                'pageHead'    => [
                    'title'   => __('New space'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-user'],
                        'title' => __('Space')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => preg_replace('/create$/', 'index', $request->route()->getName()),
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
                                        'label'    => __('Reference')
                                    ],

                                    'start_at'        => [
                                        'type'     => 'date',
                                        'required' => true,
                                        'label'    => __('start rent at')
                                    ],
                                    'end_at'          => [
                                        'type'  => 'date',
                                        'label' => __('end rent at')
                                    ],
                                    'rental_id'       => [
                                        'type'     => 'select',
                                        'required' => true,
                                        'label'    => __('rental'),
                                        'options'  => Options::forModels(Rental::where('type', RentalTypeEnum::SPACE->value)->where('fulfilment_id', $fulfilmentCustomer->fulfilment_id)->where('state', RentalStateEnum::ACTIVE))
                                    ],
                                    'exclude_weekend' => [
                                        'type'     => 'toggle',
                                        'required' => true,
                                        'value'    => false,
                                        'label'    => __('exclude weekend')
                                    ],

                                ]
                            ]
                        ],
                    'route'     => [
                        'name'       => 'grp.models.fulfilment_customer_space.store',
                        'parameters' => [
                            'fulfilmentCustomer' => $fulfilmentCustomer->id
                        ]
                    ]
                ]
            ]
        );
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Response
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilmentCustomer, $request);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowFulfilmentCustomer::make()->getBreadcrumbs(
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating space'),
                    ]
                ]
            ]
        );
    }
}
