<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:25:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\OrgAction;
use App\Enums\CRM\WebUser\WebUserTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateWebUser extends OrgAction
{
    private Customer|FulfilmentCustomer $parent;
    private Customer $customer;


    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof FulfilmentCustomer) {
            return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
        } elseif ($this->parent instanceof Customer) {
            return
            $this->canEdit   = $request->user()->authTo("crm.{$this->shop->id}.edit");
        }

        return false;
    }


    public function htmlResponse(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'    => __('Create web user'),
                'pageHead' => [
                    'title' => __('Create web user'),
                    'icon'  => [
                        'icon'  => 'fal fa-terminal',
                        'title' => __('web user')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name' => match (class_basename($this->parent)) {
                                    'Customer'           => 'grp.org.shops.show.crm.customers.show',
                                    'FulfilmentCustomer' => 'grp.org.fulfilments.show.crm.customers.show',
                                },
                                'parameters' => $request->route()->originalParameters()
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' =>
                        [
                            [
                                'fields' => [
                                    'type' => [
                                        'options' => [
                                            WebUserTypeEnum::WEB->value => [
                                                'label' => __('Customer')
                                            ],
                                            WebUserTypeEnum::API->value => [
                                                'label' => __('API user')
                                            ]
                                        ]
                                    ],
                                    'contact_name' => [
                                        'type'  => 'input',
                                        'label' => __('Contact name'),
                                        'placeholder' => __('Contact name'),
                                        'value' => ''
                                    ],
                                    'email' => [
                                        'type'  => 'input',
                                        'label' => __('email'),
                                        'placeholder' => __('example@email.com'),
                                        'value' => $this->customer->hasUsers() ? '' : $this->customer->email
                                    ],
                                    'username' => [
                                        'type'  => 'input',
                                        'placeholder' => __('username'),
                                        'label' => __('username'),
                                        'value' => ''
                                    ],
                                    'password' => [
                                        'type'  => 'password',
                                        'label' => __('password'),
                                        'value' => ''
                                    ],
                                    'is_root' => [
                                        'type'  => 'toggle',
                                        'label' => __('Admin'),
                                        'value' => false
                                    ],

                                ]
                            ]
                        ],
                    'route' =>
                        match (class_basename($this->parent)) {
                            'Customer' => [
                                'name'       => 'grp.models.customer.web-user.store',
                                'parameters' => [$this->parent->id]
                            ],
                            'FulfilmentCustomer' => [
                                'name'       => 'grp.models.fulfilment-customer.web-user.store',
                                'parameters' => [$this->parent->id]
                            ]
                        },


                ]


            ]
        );
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(
        Organisation $organisation,
        Fulfilment $fulfilment,
        FulfilmentCustomer $fulfilmentCustomer,
        ActionRequest $request
    ): ActionRequest {
        $this->parent  = $fulfilmentCustomer;
        $this->customer = $fulfilmentCustomer->customer;
        $this->initialisationFromFulfilment($fulfilment, $request);
        return $request;
    }

    public function asController(
        Organisation $organisation,
        Shop $shop,
        Customer $customer,
        ActionRequest $request
    ): ActionRequest {
        $this->parent  = $customer;
        $this->customer = $customer;
        $this->initialisationFromShop($shop, $request);
        return $request;
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return match (class_basename($this->parent)) {
            'Customer' =>
            array_merge(
                ShowCustomer::make()->getBreadcrumbs('grp.org.shops.show.crm.customers.show', $routeParameters),
                [
                    [
                        'type'          => 'creatingModel',
                        'creatingModel' => [
                            'label' => __('Creating web user'),
                        ]
                    ]
                ]
            ),

            'FulfilmentCustomer' =>
            array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'          => 'creatingModel',
                        'creatingModel' => [
                            'label' => __('Creating web user'),
                        ]
                    ]
                ]
            ),

            default => []
        };
    }
}
