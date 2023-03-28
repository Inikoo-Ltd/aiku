<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 17:41:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Sales\Customer\UI;

use App\Actions\InertiaAction;
use App\Models\Marketing\Shop;
use App\Models\Sales\Customer;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditCustomer extends InertiaAction
{
    public function handle(Customer $customer): Customer
    {
        return $customer;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->can('shops.customers.edit');
        return $request->user()->hasPermissionTo("shops.customers.edit");
    }

    public function asController(Customer $customer, ActionRequest $request): Customer
    {
        $this->initialisation($request);
        return $this->handle($customer);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Shop $shop, Customer $customer, ActionRequest $request): Customer
    {
        $this->initialisation($request);
        return $this->handle($customer);
    }

    public function htmlResponse(Customer $customer): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('customer'),
                'breadcrumbs' => ShowCustomer::make()->getBreadcrumbs($this->routeName, $customer),
                'pageHead'    => [
                    'title'    => $customer->name,
                    'exitEdit' => [
                        'route' => [
                            'name'       => preg_replace('/edit$/', 'show', $this->routeName),
                            'parameters' => array_values($this->originalParameters),
                        ]
                    ],
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title'    => __('contact information'),
                            'fields'   => [

                                'contact_name' => [
                                    'type'  => 'input',
                                    'label' => __('contact name'),
                                    'value' => $customer->contact_name
                                ],
                                'company_name' => [
                                    'type'  => 'input',
                                    'label' => __('company'),
                                    'value' => $customer->company_name
                                ],
                                'phone'        => [
                                    'type'  => 'phone',
                                    'label' => __('Phone'),
                                    'value' => $customer->phone
                                ],

                            ]
                        ]

                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'      => 'models.customer.update',
                            'parameters'=> $customer->slug

                        ],
                    ]

                ],

            ]
        );
    }
}
