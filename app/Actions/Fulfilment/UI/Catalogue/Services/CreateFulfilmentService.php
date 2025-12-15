<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:22:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\UI\Catalogue\Services;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopEditAuthorisation;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateFulfilmentService extends OrgAction
{
    use WithFulfilmentShopEditAuthorisation;

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
                'title'       => __('New service'),
                'pageHead'    => [
                    'title' => __('New service')
                ],
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('New Service'),
                                'fields' => [
                                    'code'  => [
                                        'type'     => 'input',
                                        'label'    => __('Code'),
                                        'required' => true
                                    ],
                                    'name'  => [
                                        'type'     => 'input',
                                        'label'    => __('Name'),
                                        'required' => true
                                    ],
                                    'price' => [
                                        'type'     => 'input',
                                        'label'    => __('Price'),
                                        'required' => true
                                    ],
                                    'unit'  => [
                                        'type'     => 'input',
                                        'label'    => __('unit'),
                                        'required' => true,
                                    ],
                                    'is_pallet_handling'  => [
                                        'type'     => 'toggle',
                                        'label'    => __('Pallet handling'),
                                        'required' => true,
                                        'value'    => false
                                    ],

                                ]
                            ]
                        ],
                    'route'      => [
                        'name'       => 'grp.models.org.fulfilment.services.store',
                        'parameters' => [
                            'organisation' => $fulfilment->organisation_id,
                            'fulfilment'   => $fulfilment->id,
                        ]
                    ]
                ],

            ]
        );
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
            IndexFulfilmentServices::make()->getBreadcrumbs(
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating service'),
                    ]
                ]
            ]
        );
    }

}
