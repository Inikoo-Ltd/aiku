<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Fulfilment\StoredItem\UI;

use App\Actions\OrgAction;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateStoredItem extends OrgAction
{
    public function handle(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'       => __('New SKU'),
                'pageHead'    => [
                    'title'   => __('New SKU'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-narwhal'],
                        'title' => __('SKU')
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
                                'fields' => [
                                    'reference' => [
                                        'type'     => 'input',
                                        'required' => true,
                                        'label'    => __('Reference')
                                    ],

                                    'name'        => [
                                        'type'     => 'input',
                                        'required' => true,
                                        'label'    => __('Name')
                                    ],
                                ]
                            ]
                        ],
                    'route'     => [
                        'name'       => 'grp.models.fulfilment-customer.stored-items.store',
                        'parameters' => [
                            'fulfilmentCustomer' => $fulfilmentCustomer->id
                        ],
                        'body' => [
                            'referral_route' => [
                                'name'       => 'grp.org.fulfilments.show.crm.customers.show.stored-items.show',
                                'parameters' => $request->route()->originalParameters()
                            ]
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

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexStoredItems::make()->getBreadcrumbs(
                $routeName,
                $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating SKU'),
                    ]
                ]
            ]
        );
    }
}
