<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Http\Resources\Helpers\TaxNumberResource;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditCustomer extends OrgAction
{
    use WithCRMAuthorisation;

    public function handle(Customer $customer): Customer
    {
        return $customer;
    }


    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): Customer
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($customer);
    }

    public function htmlResponse(Customer $customer, ActionRequest $request): Response
    {
        $spain = \App\Models\Helpers\Country::where('code', 'ES')->first();

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('customer'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($customer, $request),
                    'next'     => $this->getNext($customer, $request),
                ],
                'pageHead'    => [
                    'title'   => $customer->name,
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ]
                        ]
                    ],
                ],


                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('contact information'),
                            'label'  => __('contact'),
                            'fields' => [
                                'contact_name'             => [
                                    'type'  => 'input',
                                    'label' => __('contact name'),
                                    'value' => $customer->contact_name
                                ],
                                'company_name'             => [
                                    'type'  => 'input',
                                    'label' => __('company'),
                                    'value' => $customer->company_name
                                ],
                                'phone'                    => [
                                    'type'  => 'phone',
                                    'label' => __('Phone'),
                                    'value' => $customer->phone
                                ],
                                'contact_address'          => [
                                    'type'    => 'address',
                                    'label'   => __('Address'),
                                    'value'   => AddressFormFieldsResource::make($customer->address)->getArray(),
                                    'options' => [
                                        'countriesAddressData' => GetAddressData::run()
                                    ]
                                ],
                                'tax_number'               => [
                                    'type'    => 'tax_number',
                                    'label'   => __('Tax number'),
                                    'value'   => $customer->taxNumber ? TaxNumberResource::make($customer->taxNumber)->getArray() : null,
                                    'country' => $customer->address->country_code,
                                ],
                                'is_re'                    => [
                                    'type'   => 'toggle',
                                    'hidden' => $this->organisation->country_id != $spain->id || $customer->address->country_id != $spain->id,
                                    'label'  => 'Recargo de equivalencia',
                                    'value'  => $customer->is_re,

                                ],
                                'identity_document_number' => [
                                    'type'  => 'input',
                                    'label' => __('identity document number'),
                                    'value' => $customer->identity_document_number
                                ],
                            ]
                        ]
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.customer.update',
                            'parameters' => [$customer->id]

                        ],
                    ]

                ],

            ]
        );
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowCustomer::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }

    public function getPrevious(Customer $customer, ActionRequest $request): ?array
    {
        $previous = Customer::where('slug', '<', $customer->slug)->when(true, function ($query) use ($customer, $request) {
            if ($request->route()->getName() == 'shops.show.customers.show') {
                $query->where('customers.shop_id', $customer->shop_id);
            }
        })->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Customer $customer, ActionRequest $request): ?array
    {
        $next = Customer::where('slug', '>', $customer->slug)->when(true, function ($query) use ($customer, $request) {
            if ($request->route()->getName() == 'shops.show.customers.show') {
                $query->where('customers.shop_id', $customer->shop_id);
            }
        })->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Customer $customer, string $routeName): ?array
    {
        if (!$customer) {
            return null;
        }

        return match ($routeName) {
            'grp.org.shops.show.crm.customers.edit' => [
                'label' => $customer->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $customer->organisation->slug,
                        'shop'         => $customer->shop->slug,
                        'customer'     => $customer->slug
                    ]

                ]
            ]
        };
    }
}
