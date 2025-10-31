<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 May 2023 11:40:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\OrgAction;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\SerialReference;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditShop extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'shop-admin.'.$this->shop->id]);
    }

    public function handle(Shop $shop): Shop
    {
        return $shop;
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    /**
     * @throws Exception
     */
    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $merged = array_merge(
            $shop->organisation->forbidden_dispatch_countries ?? [],
            $shop->forbidden_dispatch_countries ?? []
        );

        $result = array_reduce($merged, function ($carry, $item) {
            if (!in_array($item, $carry)) {
                $carry[] = $item;
            }

            return $carry;
        }, []);


        $invoiceSerialReference = SerialReference::where('model', SerialReferenceModelEnum::INVOICE)
            ->where('container_type', 'Shop')
            ->where('container_id', $shop->id)->first();



        $refundSerialReference = SerialReference::where('model', SerialReferenceModelEnum::REFUND)
            ->where('container_type', 'Shop')
            ->where('container_id', $shop->id)->first();

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit shop'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),

                'pageHead' => [
                    'title'   => $shop->name,
                    'icon'    => [
                        'title' => __('Shop'),
                        'icon'  => 'fal fa-store-alt'
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.shops.show.dashboard.show',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'label'  => __('Shop details'),
                            'icon'   => 'fa-light fa-id-card',
                            'fields' => [
                                'code'  => [
                                    'type'     => 'input',
                                    'label'    => __('code'),
                                    'value'    => $shop->code,
                                    'required' => true,
                                ],
                                'name'  => [
                                    'type'     => 'input',
                                    'label'    => __('name'),
                                    'value'    => $shop->name,
                                    'required' => true,
                                ],
                                "image" => [
                                    "type"  => "avatar",
                                    "label" => __("Logo"),
                                    "value" => $shop->imageSources(320, 320)
                                ],

                                'contact_name'        => [
                                    'type'  => 'input',
                                    'label' => __('contact name'),
                                    'value' => $shop->contact_name,
                                ],
                                'company_name'        => [
                                    'type'  => 'input',
                                    'label' => __('company name'),
                                    'value' => $shop->company_name,
                                ],
                                'email'               => [
                                    'type'    => 'input',
                                    'label'   => __('email'),
                                    'value'   => $shop->email,
                                    'options' => [
                                        'inputType' => 'email'
                                    ]
                                ],
                                'phone'               => [
                                    'type'  => 'phone',
                                    'label' => __('telephone'),
                                    'value' => $shop->phone,
                                ],
                                'address'             => [
                                    'type'    => 'address',
                                    'label'   => __('Address'),
                                    'value'   => AddressFormFieldsResource::make($shop->address)->getArray(),
                                    'options' => [
                                        'countriesAddressData' => GetAddressData::run()
                                    ]
                                ],
                                'registration_number' => [
                                    'type'  => 'input',
                                    'label' => __('registration number'),
                                    'value' => $shop->data['registration_number'] ?? '',
                                ],
                                'vat_number'          => [
                                    'type'  => 'input',
                                    'label' => __('VAT number'),
                                    'value' => $shop->data['vat_number'] ?? '',
                                ],

                            ]
                        ],
                        [
                            'label'  => __('Properties'),
                            'icon'   => 'fa-light fa-fingerprint',
                            'fields' => [
                                'country_id'  => [
                                    'type'        => 'select',
                                    'label'       => __('country'),
                                    'placeholder' => __('Select your country'),
                                    'value'       => $shop->country_id,
                                    'options'     => GetCountriesOptions::run(),
                                    'searchable'  => true
                                ],
                                'currency_id' => [
                                    'type'        => 'select',
                                    'label'       => __('currency'),
                                    'placeholder' => __('Select your currency'),
                                    'required'    => true,
                                    'value'       => $shop->currency_id,
                                    'options'     => GetCurrenciesOptions::run(),
                                    'searchable'  => true
                                ],
                            ],
                        ],
                        [
                            'label'  => __('contact/details'),
                            'icon'   => 'fa-light fa-user',
                            'fields' => [

                            ]
                        ],
                        [
                            'label'  => __('Pricing'),
                            'icon'   => 'fa-light fa-money-bill',
                            'fields' => [
                                'cost_price_ratio' => [
                                    'type'        => 'input_number',
                                    'bind'        => [
                                        'maxFractionDigits' => 3
                                    ],
                                    'label'       => __('pricing ratio'),
                                    'placeholder' => __('Cost price ratio'),
                                    'required'    => true,
                                    'value'       => $shop->cost_price_ratio,
                                    'min'         => 0
                                ],
                                'price_rrp_ratio'  => [
                                    'type'        => 'input_number',
                                    'bind'        => [
                                        'maxFractionDigits' => 3
                                    ],
                                    'label'       => __('rrp ratio'),
                                    'placeholder' => __('price rrp ratio'),
                                    'required'    => true,
                                    'value'       => $shop->price_rrp_ratio,
                                    'min'         => 0
                                ]
                            ]
                        ],
                        [
                            'label'  => __('Registration'),
                            'icon'   => 'fal fa-transporter',
                            'fields' => [
                                'required_approval' => [
                                    'type'  => 'toggle',
                                    'label' => __('Require approval'),
                                    'value' => Arr::get($shop->settings, 'registration.require_approval', false),
                                ],
                                'required_phone_number' => [
                                    'type'  => 'toggle',
                                    'label' => __('Require phone number'),
                                    'value' => Arr::get($shop->settings, 'registration.require_phone_number', false),
                                ],

                                'marketing_opt_in_default' => [
                                    'type'  => 'toggle',
                                    'label' => __('Marketing opt-in set as checked'),
                                    'value' => Arr::get($shop->settings, 'registration.marketing_opt_in_default', false),
                                ],
                            ],
                        ],
                        [
                            'label'  => __('Invoice numbers'),
                            'icon'   => 'fal fa-file-invoice',
                            'fields' => [
                                'invoice_serial_references' => [
                                    'type'    => 'invoice_serial_references',
                                    'options' => [
                                            [
                                                'type' => [
                                                    'label' => __('Standalone invoice numbers'),
                                                    'key_value' => 'stand_alone_invoice_numbers'
                                                ],
                                                'format' => [
                                                    'label' => __('format'),
                                                    'key_value' => 'stand_alone_invoice_numbers_format'
                                                ],
                                                'sequence' => [
                                                    'label' => __('sequence'),
                                                    'key_value' => 'stand_alone_invoice_numbers_serial'
                                                ],
                                            ],
                                            [
                                                'type' => [
                                                    'label' => __('Standalone refunds numbers'),
                                                    'key_value' => 'stand_alone_refund_numbers'
                                                ],
                                                'format' => [
                                                    'label' => __('format'),
                                                    'key_value' => 'stand_alone_refund_numbers_format'
                                                ],
                                                'sequence' => [
                                                    'label' => __('sequence'),
                                                    'key_value' => 'stand_alone_refund_numbers_serial'
                                                ],
                                            ],
                                    ],
                                    'label'   => __('Invoice numbers'),
                                    'value'   => [
                                        'stand_alone_invoice_numbers'        => Arr::get($shop->settings, 'invoicing.stand_alone_invoice_numbers', false),
                                        'stand_alone_invoice_numbers_format' => $invoiceSerialReference->format,
                                        'stand_alone_invoice_numbers_serial' => $invoiceSerialReference->serial,
                                        'stand_alone_refund_numbers'         => Arr::get($shop->settings, 'invoicing.stand_alone_refund_numbers', false),
                                        'stand_alone_refund_numbers_format'  => $refundSerialReference?->format,
                                        'stand_alone_refund_numbers_serial'  => $refundSerialReference?->serial,
                                    ]
                                ],
                            ],
                        ],
                        [
                            'label'  => __('invoices footer'),
                            'icon'   => 'fa-light fa-shoe-prints',
                            'fields' => [
                                'invoice_footer' => [
                                    'type'  => 'textEditor',
                                    'label' => __('invoice footer'),
                                    'full'  => true,
                                    'value' => $shop->invoice_footer
                                ],
                            ],
                        ],
                        [
                            'label'  => __('Languages'),
                            'icon'   => 'fa-light fa-language',
                            'fields' => [
                                'language_id'     => [
                                    'type'        => 'select',
                                    'label'       => __('Main language'),
                                    'placeholder' => __('Select your language'),
                                    'required'    => true,
                                    'value'       => $shop->language_id,
                                    'options'     => GetLanguagesOptions::make()->all(),
                                    'searchable'  => true
                                ],
                                'extra_languages' => [
                                    'type'        => 'select',
                                    'label'       => __('Extra language'),
                                    'placeholder' => __('Select your language'),
                                    'required'    => true,
                                    'value'       => $shop->extra_languages,
                                    'options'     => GetLanguagesOptions::make()->getExtraGroupLanguages($shop->group->extra_languages),
                                    'searchable'  => true,
                                    'mode'        => 'tags',
                                    'labelProp'   => 'name',
                                    'valueProp'   => 'id',
                                ]
                            ],
                        ],
                        [
                            'label'  => __('Shipping'),
                            'icon'   => 'fa-light fa-truck',
                            'fields' => [
                                'forbidden_dispatch_countries' => [
                                    'type'        => 'multiselect-tags',
                                    'placeholder' => __('Select countries'),
                                    'information' => __('Customer cannot submit order that delivered to these countries'),
                                    'label'       => __('Forbidden Countries'),
                                    'required'    => true,
                                    'value'       => $result,
                                    'options'     => GetCountriesOptions::run(),
                                    'searchable'  => true,
                                    'mode'        => 'tags',
                                    'labelProp'   => 'label',
                                    'valueProp'   => 'id'
                                ]
                            ],
                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.org.shop.update',
                            'parameters' => [
                                'organisation' => $shop->organisation_id,
                                'shop'         => $shop->id
                            ]
                        ],
                    ]
                ],

            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.shops.show.settings.edit' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.settings.edit',
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
