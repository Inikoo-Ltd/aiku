<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 10:21:46 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Actions\OrgAction;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOrgSupplier extends OrgAction
{
    use WithProcurementAuthorisation;

    public function handle(OrgSupplier $orgSupplier): OrgSupplier
    {
        return $orgSupplier;
    }

    public function asController(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): OrgSupplier
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgSupplier);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, OrgSupplier $orgSupplier, ActionRequest $request): OrgSupplier
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgSupplier);
    }

    protected function ownsSupplierAgent(OrgSupplier $orgSupplier): bool
    {
        return (bool) ($orgSupplier->supplier->agent && $orgSupplier->supplier->agent->organisation_id === $this->organisation->id);
    }

    public function htmlResponse(OrgSupplier $orgSupplier, ActionRequest $request): Response
    {
        $supplier = $orgSupplier->supplier;

        $blueprint = $this->ownsSupplierAgent($orgSupplier)
            ? [
                [
                    'title' => __('ID/contact details '),
                    'icon' => 'fal fa-address-book',
                    'fields' => [
                        'code' => [
                            'type' => 'input',
                            'label' => __('Code'),
                            'value' => $supplier->code,
                            'required' => true,
                        ],
                        'company_name' => [
                            'type' => 'input',
                            'label' => __('Company'),
                            'value' => $supplier->company_name,
                        ],
                        'contact_name' => [
                            'type' => 'input',
                            'label' => __('Contact name'),
                            'value' => $supplier->contact_name,
                        ],
                        'contact_website' => [
                            'type' => 'input',
                            'label' => __('Contact website'),
                            'value' => $supplier->contact_website,
                        ],
                        'email' => [
                            'type' => 'input',
                            'label' => __('Email'),
                            'value' => $supplier->email,
                            'options' => [
                                'inputType' => 'email',
                            ],
                        ],
                        'phone' => [
                            'type' => 'phone',
                            'label' => __('phone'),
                            'value' => $supplier->phone,
                        ],
                        'address' => [
                            'type' => 'address',
                            'label' => __('Address'),
                            'value' => AddressResource::make($supplier->getAddress('contact'))->getArray(),
                            'options' => [
                                'countriesAddressData' => GetAddressData::run(),
                            ],
                        ],
                    ],
                ],
                [
                    'title' => __('settings '),
                    'icon' => 'fa-light fa-cog',
                    'fields' => [
                        'currency_id' => [
                            'type' => 'select',
                            'label' => __('Currency'),
                            'placeholder' => __('Select a currency'),
                            'options' => GetCurrenciesOptions::run(),
                            'value' => $supplier->currency_id,
                            'searchable' => true,
                            'required' => true,
                            'mode' => 'single',
                        ],
                        'default_product_country_origin' => [
                            'type' => 'select',
                            'label' => __("Asset's country of origin"),
                            'placeholder' => __('Select a country'),
                            'value' => $supplier->code,
                            'options' => GetCountriesOptions::run(),
                            'mode' => 'single',
                        ],
                    ],
                ],
            ]
            : [
                [
                    'title' => __('Status'),
                    'icon' => 'fa-light fa-cog',
                    'fields' => [
                        'status' => [
                            'type' => 'toggle',
                            'label' => __('Active'),
                            'value' => $orgSupplier->status,
                        ],
                    ],
                ],
            ];

        $updateRoute = $this->ownsSupplierAgent($orgSupplier)
            ? [
                'name' => 'grp.models.supplier.update',
                'parameters' => $supplier->id,
            ]
            : [
                'name' => 'grp.models.org_supplier.update',
                'parameters' => $orgSupplier->id,
            ];

        return Inertia::render(
            'EditModel',
            [
                'title' => __('Edit supplier'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead' => [
                    'title' => $supplier->code,
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name' => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],

                'formData' => [
                    'blueprint' => $blueprint,
                    'args' => [
                        'updateRoute' => $updateRoute,
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowOrgSupplier::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
