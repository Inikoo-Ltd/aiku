<?php
/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:34:29 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Market\RentalAgreement\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\InertiaAction;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateRentalAgreement extends InertiaAction
{
    /**
     * @throws Exception
     */
    public function handle(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'    => __('new rental agreement'),
                'pageHead' => [
                    'title' => __('new rental agreement')
                ],
                'formData' => [
                    'blueprint' =>
                        [
                            [
                                'title'  => __('name'),
                                'fields' => [
                                    'billing_cycle' => [
                                        'type'     => 'input',
                                        'label'    => __('billing cycle'),
                                        'required' => true
                                    ],
                                    'pallets_limit' => [
                                        'type'     => 'number',
                                        'label'    => __('pallets limit'),
                                        'required' => true
                                    ],
                                ]
                            ]
                        ],
                    'route' => [
                        'name'   => 'grp.models.rental-agreement.store',
                        'params' => [
                            'organisation'       => $fulfilmentCustomer->organisation_id,
                            'fulfilment'         => $fulfilmentCustomer->fulfilment_id,
                            'fulfilmentCustomer' => $fulfilmentCustomer->id,
                        ]
                    ]
                ]
            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasPermissionTo('shops.rental-agreements.edit');
    }

    /**
     * @throws Exception
     */
    public function asController(Organisation $organisation, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Response
    {
        $this->initialisation($request);

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
                        'label' => __('creating rental agreement'),
                    ]
                ]
            ]
        );
    }

}
