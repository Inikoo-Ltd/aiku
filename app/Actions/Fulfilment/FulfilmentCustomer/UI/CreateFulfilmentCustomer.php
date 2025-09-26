<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jan 2024 20:02:01 Malaysia Time, Sanur , Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Fields\StoreCustomerFields;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateFulfilmentCustomer extends OrgAction
{
    use StoreCustomerFields;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function handle(Fulfilment $fulfilment, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('new customer'),
                'pageHead'    => [
                    'title'        => __('new customer'),
                    'icon'         => [
                        'icon'  => ['fal', 'fa-user'],
                        'title' => __('Customer')
                    ],
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => preg_replace('/create$/', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => $this->getBlueprint($fulfilment->shop),

                    'route'     => [
                        'name'      => 'grp.models.fulfilment.fulfilment_customer.store',
                        'parameters' => [$fulfilment->id]
                    ]
                ]
            ]
        );
    }




    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): Response
    {
        $this->initialisationFromFulfilment($fulfilment, $request);
        return $this->handle($fulfilment, $request);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexFulfilmentCustomersApproved::make()->getBreadcrumbs(
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating customer'),
                    ]
                ]
            ]
        );
    }
}
