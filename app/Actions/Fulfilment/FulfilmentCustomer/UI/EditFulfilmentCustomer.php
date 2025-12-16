<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\OrgAction;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Http\Resources\Helpers\TaxNumberResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditFulfilmentCustomer extends OrgAction
{
    public function handle(FulfilmentCustomer $fulfilmentCustomer): FulfilmentCustomer
    {
        return $fulfilmentCustomer;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.view");
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): FulfilmentCustomer
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilmentCustomer);
    }

    public function htmlResponse(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Response
    {
        $spain = \App\Models\Helpers\Country::where('code', 'ES')->first();

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('customer'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($fulfilmentCustomer, $request),
                    'next'     => $this->getNext($fulfilmentCustomer, $request),
                ],
                'pageHead'    => [
                    'title'   => $fulfilmentCustomer->customer->name,
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.crm.customers.show',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ]
                        ]
                    ],
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('Contact information'),
                            'label'  => __('Contact'),
                            'fields' => [

                                'contact_name'             => [
                                    'type'  => 'input',
                                    'label' => __('Contact name'),
                                    'value' => $fulfilmentCustomer->customer->contact_name
                                ],
                                'company_name'             => [
                                    'type'  => 'input',
                                    'label' => __('Company'),
                                    'value' => $fulfilmentCustomer->customer->company_name
                                ],
                                'email'                    => [
                                    'type'  => 'input',
                                    'label' => __('Email'),
                                    'value' => $fulfilmentCustomer->customer->email
                                ],
                                'phone'                    => [
                                    'type'  => 'phone',
                                    'label' => __('Phone'),
                                    'value' => $fulfilmentCustomer->customer->phone
                                ],
                                'address'                  => [
                                    'type'    => 'address',
                                    'label'   => __('Address'),
                                    'value'   => AddressFormFieldsResource::make($fulfilmentCustomer->customer->address)->getArray(),
                                    'options' => [
                                        'countriesAddressData' => GetAddressData::run()
                                    ]
                                ],
                                'tax_number'               => [
                                    'type'    => 'tax_number',
                                    'label'   => __('Tax number'),
                                    'value'   => $fulfilmentCustomer->customer->taxNumber ? TaxNumberResource::make($fulfilmentCustomer->customer->taxNumber)->getArray() : null,
                                    'country' => $fulfilmentCustomer->customer->address->country_code,
                                ],
                                'is_re'                    => [
                                    'type'   => 'toggle',
                                    'hidden' => $this->organisation->country_id != $spain->id || $fulfilmentCustomer->customer->address->country_id != $spain->id,
                                    'label'  => 'Recargo de equivalencia',
                                    'value'  => $fulfilmentCustomer->customer->is_re,

                                ],
                                'identity_document_number' => [
                                    'type'  => 'input',
                                    'label' => __('Identity document number'),
                                    'value' => $fulfilmentCustomer->customer->identity_document_number
                                ],
                            ]
                        ],
                        [
                            'title'  => __('Accounting'),
                            'label'  => __('Accounting'),
                            'fields' => [


                                'is_credit_customer'   => [
                                    'type'  => 'toggle',
                                    'label' => __('Credit Customer'),
                                    'value' => $fulfilmentCustomer->customer->is_credit_customer,
                                ],
                                'accounting_reference' => [
                                    'type'     => 'input',
                                    'label'    => __('Sage Customer Number'),
                                    'value'    => $fulfilmentCustomer->customer->accounting_reference,
                                    'required' => false,
                                ],
                            ]
                        ]
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.fulfilment-customer.update',
                            'parameters' => [$fulfilmentCustomer->id]
                        ],
                    ]

                ],

            ]
        );
    }


    public function getBreadcrumbs(array $routeParameters): array
    {
        return ShowFulfilmentCustomer::make()->getBreadcrumbs(
            routeParameters: $routeParameters,
        );
    }

    public function getPrevious(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): ?array
    {
        $previous = FulfilmentCustomer::where('slug', '<', $fulfilmentCustomer->slug)->when(true, function ($query) use ($fulfilmentCustomer, $request) {
            if ($request->route()->getName() == 'shops.show.customers.show') {
                $query->where('customers.shop_id', $fulfilmentCustomer->customer->shop_id);
            }
        })->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): ?array
    {
        $next = FulfilmentCustomer::where('slug', '>', $fulfilmentCustomer->slug)->when(true, function ($query) use ($fulfilmentCustomer, $request) {
            if ($request->route()->getName() == 'shops.show.customers.show') {
                $query->where('customers.shop_id', $fulfilmentCustomer->customer->shop_id);
            }
        })->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?FulfilmentCustomer $fulfilmentCustomer, string $routeName): ?array
    {
        if (!$fulfilmentCustomer) {
            return null;
        }

        return match ($routeName) {
            'grp.org.fulfilments.show.crm.customers.show.edit' => [
                'label' => $fulfilmentCustomer->customer->contact_name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'       => $fulfilmentCustomer->organisation->slug,
                        'fulfilment'         => $fulfilmentCustomer->fulfilment->slug,
                        'fulfilmentCustomer' => $fulfilmentCustomer->slug
                    ]
                ]
            ]
        };
    }
}
