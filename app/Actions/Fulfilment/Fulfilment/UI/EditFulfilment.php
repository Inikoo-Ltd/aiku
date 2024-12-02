<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jul 2024 16:42:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment\UI;

use App\Actions\OrgAction;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditFulfilment extends OrgAction
{
    public function handle(Fulfilment $fulfilment): Fulfilment
    {
        return $fulfilment;
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): Fulfilment
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }


    public function htmlResponse(Fulfilment $fulfilment, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('fulfilment setting'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'    => $fulfilment->shop->name,
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('recurring bill settings'),
                            'label'  => __('cut off day'),
                            'fields' => [
                                'monthly_cut_off' => [
                                    'type'      => 'date_radio',
                                    'label'     => __('monthly cut off day'),
                                    'options'   => [
                                        1, 2, 3, 4, 5, 6, 7, 8 ,9, 10,
                                        11, 12, 13, 14, 15, 16, 17, 18 ,19, 20,
                                        21, 22, 23, 24, 25, 26, 27, 28 ,29, 30,
                                        31
                                    ],
                                    'value' => [
                                        'date'          => $fulfilment->settings['rental_agreement_cut_off']['monthly']['day'],
                                        'isWeekdays'    => $fulfilment->settings['rental_agreement_cut_off']['monthly']['workdays'],
                                    ]
                                ],
                                'weekly_cut_off_day' => [
                                    'type'      => 'radio',
                                    'mode'      => 'compact',
                                    'options'   => [
                                        [
                                            'label' => __('Monday'),
                                            'value' => 'Monday'
                                        ],
                                        [
                                            'label' => __('Tuesday'),
                                            'value' => 'Tuesday'
                                        ],
                                        [
                                            'label' => __('Wednesday'),
                                            'value' => 'Wednesday'
                                        ],
                                        [
                                            'label' => __('Thursday'),
                                            'value' => 'Thursday'
                                        ],
                                        [
                                            'label' => __('Friday'),
                                            'value' => 'Friday'
                                        ],
                                        [
                                            'label' => __('Saturday'),
                                            'value' => 'Saturday'
                                        ],
                                        [
                                            'label' => __('Sunday'),
                                            'value' => 'Sunday'
                                        ],

                                    ],
                                    'valueProp' => 'value',
                                    'required'  => true,
                                    'label'     => __('weekly cut off day'),
                                    'value'     => $fulfilment->settings['rental_agreement_cut_off']['weekly']['day']
                                ]
                            ]
                        ]

                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.fulfilment.update',
                            'parameters' => [$fulfilment->id]
                        ],
                    ]

                ],

            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.fulfilments.show.settings.edit' =>
               array_merge(
                   ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                   [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'route' => [
                                    'name'       => 'grp.org.fulfilments.show.settings.edit',
                                    'parameters' => $routeParameters
                                ],
                                'label' => __('Settings')
                            ]
                        ]
                    ]
               ),
            default => []
        };
    }


}
