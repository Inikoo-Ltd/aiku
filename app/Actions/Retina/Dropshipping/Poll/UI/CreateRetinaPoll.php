<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Poll\UI;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Inertia\Inertia;
use Spatie\LaravelOptions\Options;
use Lorisleiva\Actions\ActionRequest;

class CreateRetinaPoll extends RetinaAction
{
    use WithCustomersSubNavigation;

    /**
     * @throws \Exception
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): \Inertia\Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'title'       => __('poll'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'   => __('new poll'),
                    'icon'    => [
                        'title' => __('polls'),
                        'icon'  => 'fal fa-cube'
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => preg_replace('/create$/', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('new poll'),
                            'fields' => [
                                'name'                     => [
                                    'type'     => 'input',
                                    'label'    => __('name'),
                                    'required' => true
                                ],
                                'type'                     => [
                                    'type'     => 'poll_type_select',
                                    'label'    => __('type'),
                                    'required' => true,
                                    'options'  => Options::forEnum(PollTypeEnum::class),
                                    'value'    => [
                                        'type'         => PollTypeEnum::OPTION->value,
                                        'poll_options' => []
                                    ]
                                ],
                                'label'                    => [
                                    'type'     => 'input',
                                    'label'    => __('label'),
                                    'required' => true
                                ],
                                'in_registration'          => [
                                    'type'  => 'toggle',
                                    'label' => __('in registration'),
                                    'value' => false
                                ],
                                'in_registration_required' => [
                                    'type'  => 'toggle',
                                    'label' => __('registration required'),
                                    'value' => false
                                ],
                            ],
                        ]
                    ],

                    'route' => [
                        'name'       => 'grp.models.poll.store',
                        'parameters' => [
                            'customerSalesChannel' => $customerSalesChannel->id,
                        ]
                    ]
                ]
            ]
        );
    }

    /**
     * @throws \Exception
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): \Inertia\Response
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexRetinaPolls::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating poll'),
                    ]
                ]
            ]
        );
    }
}
