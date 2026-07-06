<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 May 2023 11:40:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\CRM\Customer\GoogleAds\ConnectShopGoogleAds;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Review\ReviewAutoPublishingEnum;
use App\Enums\Catalogue\Review\ReviewContextEnum;
use App\Enums\Catalogue\Review\ReviewRatingDimensionEnum;
use App\Enums\Catalogue\Review\ReviewValidationScopeEnum;
use App\Models\Reviews\ReviewRatingLabel;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\SerialReference;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Ordering\SalesChannel;

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
        $mergedBannedCountryRegions = $shop->banned_country_regions;

        $invoiceSerialReference = SerialReference::where('model', SerialReferenceModelEnum::INVOICE)
            ->where('container_type', 'Shop')
            ->where('container_id', $shop->id)->first();


        $refundSerialReference = SerialReference::where('model', SerialReferenceModelEnum::REFUND)
            ->where('container_type', 'Shop')
            ->where('container_id', $shop->id)->first();

        $firstMasterFamily = $shop->masterShop?->masterProductCategories()
            ->where('type', MasterProductCategoryTypeEnum::FAMILY)
            ->orderBy('id')
            ->first();

        $helpPortalFields = [
            'portal_link' => [
                'type'        => 'input',
                'placeholder' => 'https://example.com',
                'label'       => __('Portal Link'),
                'value'       => Arr::get($shop->settings, 'portal.link', ''),
            ]
        ];

        // Disable Widget_Key input if the shop doesn't have any related website
        if ($shop->website) {
            $helpPortalFields['widget_key'] = [
                'type'        => 'input',
                'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
                'label'       => __('Widget Key'),
                'value'       => Arr::get($shop->website->settings, 'jira_help_desk_widget'),
            ];
        }

        $isExternal = $shop->type === ShopTypeEnum::EXTERNAL;

        $isGoogleAdsConnected = filled(Arr::get($shop->settings, 'google_ads.refresh_token'));

        $allowedBlueprintLabels = [
            __('Faire Settings'),
            __('Shopify Keys'),
            __('Wix Keys'),
        ];
        $salesChannels          = SalesChannel::orderBy('id')->get();
        $salesChannelFields     = [];
        /** @var SalesChannel $channel */
        foreach ($salesChannels as $channel) {
            if ($channel->type == SalesChannelTypeEnum::WEBSITE || $channel->type == SalesChannelTypeEnum::NA) {
                continue;
            }
            $salesChannelFields['sales_channel_'.$channel->id] = [
                'label'       => __($channel->name),
                'type'        => 'toggle',
                'value'       => $shop->salesChannels->contains($channel->id),
                'information' => __('Enable the :name sales channel. Active means it is available for this shop; inactive means it is not available for this shop.', ['name' => $channel->name]),
            ];
        }

        $formData = [
            'blueprint' => [
                [
                    'label'  => __('Shop details'),
                    'icon'   => 'fa-light fa-id-card',
                    'fields' => [
                        'code'  => [
                            'type'     => 'input',
                            'label'    => __('Code'),
                            'value'    => $shop->code,
                            'required' => true,
                        ],
                        'name'  => [
                            'type'     => 'input',
                            'label'    => __('Name'),
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
                            'label' => __('Contact name'),
                            'value' => $shop->contact_name,
                        ],
                        'company_name'        => [
                            'type'  => 'input',
                            'label' => __('Company name'),
                            'value' => $shop->company_name,
                        ],
                        'email'               => [
                            'type'    => 'input',
                            'label'   => __('Email'),
                            'value'   => $shop->email,
                            'options' => [
                                'inputType' => 'email'
                            ]
                        ],
                        'phone'               => [
                            'type'  => 'phone',
                            'label' => __('Telephone'),
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
                            'label' => __('Registration number'),
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
                            'label'       => __('Country'),
                            'placeholder' => __('Select your country'),
                            'value'       => $shop->country_id,
                            'options'     => GetCountriesOptions::run(),
                            'searchable'  => true
                        ],
                        'currency_id' => [
                            'type'        => 'select',
                            'label'       => __('Currency'),
                            'placeholder' => __('Select your currency'),
                            'required'    => true,
                            'value'       => $shop->currency_id,
                            'options'     => GetCurrenciesOptions::run(),
                            'searchable'  => true
                        ],
                    ],
                ],
                $shop->masterShop ? [
                    'label'  => __('Catalogue'),
                    'icon'   => 'fal fa-books',
                    'fields' => [
                        'collection_follow_master'                 => [
                            'label'       => __('Collection Content Follow Master'),
                            'type'        => 'toggle',
                            'value'       => data_get($shop->settings, 'catalog.collection_follow_master', false),
                            'information' => __('This would force all Collections under this shop to follow any updates done on master'),
                            'warningText' => __('Changing this would determine whether or not local changes will be overwritten when the master is updated. Are you sure you want to change it?')
                        ],
                        'department_follow_master'                 => [
                            'label'       => __('Department Content Follow Master'),
                            'type'        => 'toggle',
                            'value'       => data_get($shop->settings, 'catalog.department_follow_master', false),
                            'information' => __('This would force all Departments under this shop to follow any updates done on master'),
                            'warningText' => __('Changing this would determine whether or not local changes will be overwritten when the master is updated. Are you sure you want to change it?')
                        ],
                        'sub_department_follow_master'             => [
                            'label'       => __('Sub Department Content Follow Master'),
                            'type'        => 'toggle',
                            'value'       => data_get($shop->settings, 'catalog.sub_department_follow_master', false),
                            'information' => __('This would force all Sub Departments under this shop to follow any updates done on master'),
                            'warningText' => __('Changing this would determine whether or not local changes will be overwritten when the master is updated. Are you sure you want to change it?')
                        ],
                        'family_follow_master'                     => [
                            'label'       => __('Family Content Follow Master'),
                            'type'        => 'toggle',
                            'value'       => data_get($shop->settings, 'catalog.family_follow_master', false),
                            'information' => __('This would force all Families under this shop to follow any updates done on master'),
                            'warningText' => __('Changing this would determine whether or not local changes will be overwritten when the master is updated. Are you sure you want to change it?')
                        ],
                        'product_follow_master'                    => [
                            'label'       => __('Product Content Follow Master'),
                            'type'        => 'toggle',
                            'value'       => data_get($shop->settings, 'catalog.product_follow_master', false),
                            'information' => __('This would force all Products under this shop to follow any updates done on master'),
                            'warningText' => __('Changing this would determine whether or not local changes will be overwritten when the master is updated. Are you sure you want to change it?')
                        ],
                        'family_indexing_follow_master'            => [
                            'label'       => __('Family Page Product Index Follow Master'),
                            'type'        => 'toggle',
                            'value'       => data_get($shop->settings, 'catalog.family_indexing_follow_master', true),
                            'information' => __('This would force all Products under this shop to follow the family indexing updates done on master'),
                            'warningText' => __('Changing this would determine whether or not local changes will be overwritten when the master is updated. Are you sure you want to change it?')
                        ],
                        'related_product_follow_master'            => [
                            'label'            => __('Related Product Follow Master'),
                            'type'             => 'toggle',
                            'value'            => data_get($shop->settings, 'catalog.related_product_follow_master', false),
                            'information'      => __('This would force related products under this shop to follow any updates done on master'),
                            'warningText'      => __('Changing this would determine whether or not local changes will be overwritten when the master is updated. Are you sure you want to change it?'),
                            'description'      => [
                                __('Related products are the products that are recommended to customers when they view a product category page.'),
                                __('Enabling this would force all of this shop related products to follow master shop related products.'),
                                $firstMasterFamily ? __('You can setup the products listed in @manage_related_products@ (Family :familyCode)', ['familyCode' => $firstMasterFamily->code]) : '',
                            ],
                            'descriptionLinks' => [
                                'manage_related_products' => [
                                    'label' => __('related products tab'),
                                    'route' => $firstMasterFamily ? [
                                        'name'       => 'grp.masters.master_shops.show.master_families.show',
                                        'parameters' => [
                                            'masterShop'   => $shop->masterShop->slug,
                                            'masterFamily' => $firstMasterFamily->slug,
                                            'tab'          => 'related_products',
                                        ],
                                    ] : null,
                                ],
                            ],
                        ],
                        'related_product_categories_follow_master' => [
                            'label'       => __('Related Product Category Follow Master'),
                            'type'        => 'toggle',
                            'value'       => data_get($shop->settings, 'catalog.related_product_categories_follow_master', true),
                            'information' => __('This would force related product categories under this shop to follow any updates done on master'),
                            'warningText' => __('Changing this would determine whether or not local changes will be overwritten when the master is updated. Are you sure you want to change it?')
                        ],
                    ]
                ] : [],
                [
                    'label'  => __('Pricing'),
                    'icon'   => 'fa-light fa-money-bill',
                    'fields' => [
                        'cost_price_ratio'                => [
                            'type'        => 'input_number',
                            'bind'        => [
                                'maxFractionDigits' => 3
                            ],
                            'label'       => __('Pricing ratio'),
                            'placeholder' => __('Cost price ratio'),
                            'required'    => true,
                            'value'       => $shop->cost_price_ratio,
                            'min'         => 0
                        ],
                        'price_rrp_ratio'                 => [
                            'type'        => 'input_number',
                            'bind'        => [
                                'maxFractionDigits' => 3
                            ],
                            'label'       => __('RRP ratio'),
                            'placeholder' => __('price rrp ratio'),
                            'required'    => true,
                            'value'       => $shop->price_rrp_ratio,
                            'min'         => 0
                        ],
                        'follow_master_pricing'           => [
                            'label'       => __('Follow Master Pricing'),
                            'type'        => 'toggle',
                            'value'       => data_get($shop->settings, 'catalog.follow_master_pricing', false),
                            'information' => __('Enabling this would force all of this shop prices to follow master shop prices using the set exchange ratio'),
                            'warningText' => __('Enabling this would force all of this shop prices to follow master shop prices using the set exchange ratio').
                                '. '.__(':__amountProducts Products would be updated', ['__amountProducts' => $shop->products()->count()]).'. '.__('Are you sure you want to do this?'),
                        ],
                        'product_price_currency_exchange' => [
                            'type'             => 'input_number',
                            'bind'             => [
                                'step'              => '0.5',
                                'maxFractionDigits' => 3,
                                'min'               => 0
                            ],
                            'saveConfirmation' => [
                                'title'       => __('Are you sure want to update currency exchange?'),
                                'description' => __("This will affect all products in the shop, including the product that in customer's basket. Products that already purchased in Order will not affected."),
                                'yesLabel'    => __('Yes, update currency exchange')
                            ],
                            'label'            => __('Product Currency Exchange'),
                            'placeholder'      => __('Product Currency Exchange'),
                            'required'         => true,
                            'hidden'           => app()->isProduction(),
                            'value'            => $shop->product_price_currency_exchange
                                ?? GetCurrencyExchange::run($shop->currency, $shop->currency),
                        ]
                    ]
                ],
                [
                    'label'  => __('Customers'),
                    'icon'   => 'fal fa-user',
                    'fields' => [
                        'identity_document_number_label'     => [
                            'type'        => 'input',
                            'label'       => __('Identity Document Number Label'),
                            'information' => __('The label would replace all of the Identity Document Number text under this shop'),
                            'value'       => data_get($shop->settings, 'customer.identity_document_number', ''),
                        ],
                        'identity_document_number_alt_label' => [
                            'type'        => 'input',
                            'label'       => __('Identity Document Number Alt. Label'),
                            'information' => __('The label would replace all of the Identity Document Number Alt. text under this shop'),
                            'value'       => data_get($shop->settings, 'customer.identity_document_number_alt', ''),
                        ],
                    ],
                ],
                [
                    'label'  => __('Registration'),
                    'icon'   => 'fal fa-transporter',
                    'fields' => [
                        'required_approval'     => [
                            'type'  => 'toggle',
                            'label' => __('Require approval'),
                            'value' => Arr::get($shop->settings, 'registration.require_approval', false),
                        ],
                        'required_phone_number' => [
                            'type'  => 'toggle',
                            'label' => __('Require phone number'),
                            'value' => Arr::get($shop->settings, 'registration.require_phone_number', false),
                        ],

                        'marketing_opt_in_label'   => [
                            'type'        => 'input',
                            'label'       => __('Marketing opt-in label'),
                            'placeholder' => __('Opt in to our newsletter for updates and offers.'),
                            'value'       => Arr::get($shop->settings, 'registration.marketing_opt_in_label', ''),
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
                                    'type'     => [
                                        'label'     => __('Standalone invoice numbers'),
                                        'key_value' => 'stand_alone_invoice_numbers'
                                    ],
                                    'format'   => [
                                        'label'     => __('Format'),
                                        'key_value' => 'stand_alone_invoice_numbers_format'
                                    ],
                                    'sequence' => [
                                        'label'     => __('Last incremental number'),
                                        'key_value' => 'stand_alone_invoice_numbers_serial'
                                    ],
                                ],
                                [
                                    'type'     => [
                                        'label'     => __('Standalone refunds numbers'),
                                        'key_value' => 'stand_alone_refund_numbers'
                                    ],
                                    'format'   => [
                                        'label'     => __('Format'),
                                        'key_value' => 'stand_alone_refund_numbers_format'
                                    ],
                                    'sequence' => [
                                        'label'     => __('Last incremental number'),
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
                    'label'  => __('Proforma footer'),
                    'icon'   => 'fa-light fa-shoe-prints',
                    'fields' => [
                        'proforma_footer' => [
                            'type'  => 'textEditor',
                            'label' => __('Proforma footer'),
                            'full'  => true,
                            'value' => $shop->proforma_footer
                        ],
                    ],
                ],
                [
                    'label'  => __('Invoices footer'),
                    'icon'   => 'fa-light fa-shoe-prints',
                    'fields' => [
                        'invoice_footer' => [
                            'type'  => 'textEditor',
                            'label' => __('Invoice footer'),
                            'full'  => true,
                            'value' => $shop->invoice_footer
                        ],
                    ],
                ],
                [
                    'label'       => __('Bank Transfer Instructions for Email'),
                    'icon'        => 'fa-light fa-envelope',
                    'information' => __('This information will be appended to the order confirmation email when the customer selects bank transfer as the payment method'),
                    'fields'      => [
                        'bank_transfer_instructions_for_email' => [
                            'type'  => 'textEditor',
                            'label' => __('Bank Transfer Instructions for Email'),
                            'full'  => true,
                            'value' => $shop->settings['bank_transfer_instructions_for_email'] ?? ''
                        ],
                    ],
                ],
                [
                    'label'  => __('Invoice PDF columns'),
                    'icon'   => 'fal fa-columns',
                    'fields' => [
                        'download_pdf_columns' => [
                            'type'        => 'checkbox',
                            'label'       => __('Data to display in PDF'),
                            'information' => __('Default data to include in invoice PDF'),
                            'value'       => (function () use ($shop): array {
                                $savedColumns = Arr::get($shop->settings, 'invoicing.download_pdf_columns', []);
                                $columns      = [
                                    ['label' => __('Pro mode'), 'key' => 'pro_mode'],
                                    ['label' => __('Recommended retail prices'), 'key' => 'rrp'],
                                    ['label' => __('Parts'), 'key' => 'parts'],
                                    ['label' => __('Commodity Codes'), 'key' => 'commodity_codes'],
                                    ['label' => __('Barcode'), 'key' => 'barcode'],
                                    ['label' => __('Weight'), 'key' => 'weight'],
                                    ['label' => __('Country of Origin'), 'key' => 'country_of_origin'],
                                    ['label' => __('Hide Payment Status'), 'key' => 'hide_payment_status'],
                                    ['label' => __('CPNP'), 'key' => 'cpnp'],
                                    ['label' => __('Group by Tariff Code'), 'key' => 'group_by_tariff_code'],
                                    ['label' => __('Show Dispatch Totals (SKO & Units)'), 'key' => 'show_dispatch_totals'],
                                ];

                                return array_map(fn ($col) => [
                                    'label' => $col['label'],
                                    'key'   => $col['key'],
                                    'value' => (bool)Arr::get($savedColumns, $col['key'], false),
                                ], $columns);
                            })(),
                        ],
                    ],
                ],
                [
                    'label'  => __('Languages'),
                    'icon'   => 'fa-light fa-language',
                    'fields' => [
                        'language_id' => [
                            'type'        => 'select',
                            'label'       => __('Main language'),
                            'placeholder' => __('Select your language'),
                            'required'    => true,
                            'value'       => $shop->language_id,
                            'options'     => GetLanguagesOptions::make()->all(),
                            'searchable'  => true
                        ],
                    ],
                ],

                [
                    'label' => __('Banned Countries').' ('.__('territories').')',
                    'icon'  => 'fa-light fa-ban',

                    'fields' => [
                        'banned_countries' => [
                            'full'     => true,
                            'type'     => 'banned-countries',
                            'label'    => __('Banned Countries'),
                            'required' => true,
                            'value'    => [
                                'banned_list'                        => $mergedBannedCountryRegions,
                                'is_follow_organisation_banned_list' => Arr::get($shop->settings, 'banned_countries.is_follow_organisation_banned_list', false),
                            ],
                            'options'  => GetCountriesOptions::run(true, true),
                        ],
                    ],
                ],

                $shop->type === ShopTypeEnum::DROPSHIPPING ? [
                    'label'  => __('Ebay Redirect Key'),
                    'icon'   => 'fa-light fa-key',
                    'fields' => [
                        'ebay_redirect_key'      => [
                            'type'  => 'input',
                            'label' => __('Ebay Redirect Key'),
                            'value' => Arr::get($shop->settings, 'ebay.redirect_key', ''),
                        ],
                        'ebay_marketplace_id'    => [
                            'type'  => 'input',
                            'label' => __('Ebay Marketplace Id'),
                            'value' => Arr::get($shop->settings, 'ebay.marketplace_id', ''),
                        ],
                        'ebay_warehouse_city'    => [
                            'type'  => 'input',
                            'label' => __('Ebay Warehouse City'),
                            'value' => Arr::get($shop->settings, 'ebay.warehouse_city', ''),
                        ],
                        'ebay_warehouse_state'   => [
                            'type'  => 'input',
                            'label' => __('Ebay Warehouse State'),
                            'value' => Arr::get($shop->settings, 'ebay.warehouse_state', ''),
                        ],
                        'ebay_warehouse_country' => [
                            'type'    => 'select',
                            'label'   => __('Ebay Warehouse Country'),
                            'value'   => Arr::get($shop->settings, 'ebay.warehouse_country', ''),
                            'options' => GetCountriesOptions::run(),
                            'mode'    => 'single'
                        ],
                    ],
                ] : [],
                $shop->type === ShopTypeEnum::EXTERNAL ?
                    match ($shop->engine) {
                        ShopEngineEnum::FAIRE => [
                            'label'  => __('Faire Settings'),
                            'icon'   => 'fa-light fa-key',
                            'fields' => [
                                'faire_access_token'                                      => [
                                    'type'         => 'input_with_warning',
                                    'label'        => __('Faire Access Token'),
                                    'value'        => Arr::get($shop->settings, 'faire.access_token', ''),
                                    'showWarning'  => !is_null($shop->external_shop_connection_failed_at),
                                    'warningTitle' => __('We are having troubles connecting to the platform'),
                                    'warningBody'  => __('Error Message').": ".$shop->external_shop_connection_error
                                ],
                                'faire_order_from_days'                                   => [
                                    'type'  => 'input',
                                    'label' => __('Faire Order From Days'),
                                    'value' => Arr::get($shop->settings, 'faire.order_from_days', '6')
                                ],
                                'faire_is_shipping_by_external'                           => [
                                    'type'  => 'toggle',
                                    'label' => __('Shipping by external service'),
                                    'value' => Arr::get($shop->settings, 'faire.is_shipping_by_external', false)
                                ],
                                'faire_dont_send_first_orders_automatically_to_warehouse' => [
                                    'type'  => 'toggle',
                                    'label' => __('Do not send first orders to warehouse'),
                                    'value' => Arr::get($shop->settings, 'faire.dont_send_first_orders_automatically_to_warehouse', false)
                                ]
                            ],
                        ],
                        ShopEngineEnum::WIX => [
                            'label'  => __('Wix Keys'),
                            'icon'   => 'fa-light fa-key',
                            'fields' => [
                                'wix_access_token' => [
                                    'type'  => 'input',
                                    'label' => __('Wix Access Token'),
                                    'value' => Arr::get($shop->settings, 'wix.access_token', ''),
                                ]
                            ],
                        ],
                        ShopEngineEnum::SHOPIFY => [
                            'label'  => __('Shopify Keys'),
                            'icon'   => 'fa-light fa-key',
                            'fields' => [
                                'shop_url' => [
                                    'type'     => 'input',
                                    'disabled' => true,
                                    'label'    => __('Shopify Shop Url'),
                                    'value'    => Arr::get($shop->settings, 'shopify.shop_url', ''),
                                ],
                            ],
                        ],
                        default => []
                    } : [],
                [
                    'label'  => __('Chat'),
                    'icon'   => 'fal fa-comment-alt',
                    'fields' => [
                        'enable_chat'         => [
                            'type'        => 'toggle',
                            'information' => __('If active, will enable the Chat feature on this shop website'),
                            'label'       => __('Enable Chat Feature'),
                            'value'       => Arr::get($shop->settings, 'chat.enable_chat', false),
                        ],
                        'chat_slack_token'    => [
                            'type'        => 'input',
                            'label'       => __('Slack Bot Token'),
                            'placeholder' => 'xoxb-xxxxxxxxxxxx-xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxx',
                            'information' => __('Bot User OAuth Token from your Slack App. Used to share conversations to Slack.'),
                            'value'       => Arr::get($shop->settings, 'chat.slack_token') ?? '',
                        ],
                        'chat_slack_channels' => [
                            'type'        => 'tags',
                            'label'       => __('Slack Channels'),
                            'placeholder' => '#general',
                            'information' => __('Slack channels where chat conversations will be shared. Press Enter to add each channel.'),
                            'value'       => Arr::get($shop->settings, 'chat.slack_channels') ?? [],
                        ],
                    ],
                ],
                [
                    'label'       => __('Google Ads'),
                    'icon'        => 'fa-brands fa-google',
                    'information' => $isGoogleAdsConnected
                        ? __('This shop is connected to Google Ads. Set the Customer ID and User List ID below to sync customers to your Google Ads user list.')
                        : __('Connect your Google account to authorize syncing customers, then set the Customer ID and User List ID below.'),
                    'fields'      => [
                        'gads__connect'          => [
                            'type'        => 'action',
                            'label'       => __('Google Account'),
                            'information' => $isGoogleAdsConnected
                                ? __('Connected.')
                                : __('Not connected yet.'),
                            'action'      => [
                                'type'  => 'button',
                                'style' => $isGoogleAdsConnected ? 'tertiary' : 'save',
                                'icon'  => ['fab', 'fa-google'],
                                'label' => $isGoogleAdsConnected ? __('Reconnect Google account') : __('Connect Google account'),
                                'route' => [
                                    'url' => ConnectShopGoogleAds::run($shop)
                                ],
                            ],
                        ],
                        'gads_customer_id'       => [
                            'type'        => 'input',
                            'label'       => __('Customer ID'),
                            'placeholder' => '123-456-7890',
                            'value'       => Arr::get($shop->settings, 'google_ads.customer_id', ''),
                        ],
                        'gads_login_customer_id' => [
                            'type'        => 'input',
                            'label'       => __('Manager (MCC) ID'),
                            'placeholder' => __('Only if the account is accessed through a manager account'),
                            'value'       => Arr::get($shop->settings, 'google_ads.login_customer_id', ''),
                        ],
                        'gads_user_list_id'      => [
                            'type'  => 'input',
                            'label' => __('User List ID'),
                            'value' => Arr::get($shop->settings, 'google_ads.user_list_id', ''),
                        ],
                    ],
                ],
                [
                    'label'  => __('HELP Portal'),
                    'icon'   => 'fal fa-life-ring',
                    'fields' => $helpPortalFields,
                ],
                [
                    'label'  => __('Sales Channels'),
                    'icon'   => 'fal fa-shopping-cart',
                    'fields' => $salesChannelFields,
                ],
                [
                    'label'  => __('Bundle Discount'),
                    'icon'   => 'fal fa-shopping-cart',
                    'fields' => [
                        'bundle_discount_percentage' => [
                            'type'  => 'input',
                            'label' => __('Bundle Discount Percentage'),
                            'value' => Arr::get($shop->settings, 'discount.bundle_discount_percentage', ''),
                        ],
                    ],
                ],

                [
                    'label'  => __('Reviews'),
                    'icon'   => 'fal fa-star',
                    'fields' => [
                        'reviews'                        => [
                            'type'        => 'toggle',
                            'label'       => __('Enable reviews'),
                            'information' => __('Enable the reviews feature for this shop.'),
                            'value'       => $shop->settings['reviews']['enabled'] ?? true,
                        ],
                        'review_rating_labels'           => [
                            'type'  => 'review_rating_labels',
                            'label' => __('Review rating labels'),
                            'value' => $this->loadReviewRatingLabels($shop),
                        ],
                        'review_visibility'              => [
                            'type'        => 'review_visibility',
                            'label'       => __('Visibility'),
                            'information' => __('Visibility modes available to customers (one or both).'),
                            'value'       => [
                                'visibility' => [
                                    'private' => Arr::get($shop->settings, 'reviews.visibility.private', false),
                                    'public'  => Arr::get($shop->settings, 'reviews.visibility.public', true),
                                ],
                            ],
                        ],
                        'review_publishing'              => [
                            'type'        => 'review_publishing',
                            'label'       => __('Publishing'),
                            'information' => __('When public reviews are published after submission.'),
                            'options'     => ReviewAutoPublishingEnum::selectOptions(),
                            'value'       => [
                                'auto_publishing' => [
                                    'mode'        => Arr::get($shop->settings, 'reviews.auto_publishing.mode', ReviewAutoPublishingEnum::IMMEDIATELY->value),
                                    'delay_hours' => Arr::get($shop->settings, 'reviews.auto_publishing.delay_hours', 24),
                                ],
                            ],
                        ],
                        'review_approval_required'       => [
                            'type'        => 'toggle',
                            'label'       => __('Require approval before publishing'),
                            'information' => __('When enabled, customer reviews must be approved by an admin before they are published.'),
                            'value'       => Arr::get($shop->settings, 'reviews.data.approval_required', false),
                        ],
                        'review_hours_after_dispatched'  => [
                            'type'        => 'input_number',
                            'label'       => __('Hours after dispatch before review is available'),
                            'information' => __('Number of hours after an order is dispatched before the review menu appears to the customer.'),
                            'value'       => Arr::get($shop->settings, 'reviews.data.hours_after_dispatched', 24),
                            'min'         => 1,
                        ],
                        'review_public_rating_threshold' => [
                            'type'        => 'input_number',
                            'label'       => __('Public rating threshold'),
                            'information' => __('If a customer rates higher than this value, the review is automatically made public.'),
                            'bind'        => [
                                'min' => 1,
                                'max' => 5,
                            ],
                            'value'       => Arr::get($shop->settings, 'reviews.public_rating_threshold', 3),
                        ],
                        'review_minimum_rating_to_show'  => [
                            'type'        => 'input_number',
                            'label'       => __('Minimum rating to show on website'),
                            'information' => __('Only reviews with a rating equal to or above this value are shown on the website.'),
                            'bind'        => [
                                'min' => 1,
                                'max' => 5,
                            ],
                            'value'       => Arr::get($shop->settings, 'reviews.minimum_rating_to_show', 3),
                        ],
                        'review_minimum_reviews_to_show' => [
                            'type'        => 'input_number',
                            'label'       => __('Minimum number of reviews to show on website'),
                            'information' => __('The reviews section is only shown on the website when there are at least this many reviews.'),
                            'bind'        => [
                                'min' => 0,
                            ],
                            'value'       => Arr::get($shop->settings, 'reviews.minimum_reviews_to_show', 0),
                        ],
                        'review_allow_reactions'         => [
                            'type'        => 'toggle',
                            'label'       => __('Allow likes/dislikes'),
                            'information' => __('Allow customers to like or dislike reviews left by other customers.'),
                            'value'       => Arr::get($shop->settings, 'reviews.allow_reactions', true),
                        ],
                        'review_allow_reply_reactions'   => [
                            'type'        => 'toggle',
                            'label'       => __('Allow likes/dislikes on replies'),
                            'information' => __('Allow customers to like or dislike replies to reviews.'),
                            'value'       => Arr::get($shop->settings, 'reviews.allow_reply_reactions', true),
                        ],
                        'review_show_staff_who_reply'    => [
                            'type'        => 'toggle',
                            'label'       => __('Show staff who reply'),
                            'information' => __('Show the name of the staff member who replied to a review.'),
                            'value'       => Arr::get($shop->settings, 'reviews.show_staff_who_reply', false),
                        ],
                        'review_validation_scope'   => [
                            'type'        => 'review_validation_scope',
                            'label'       => __('Include other shops'),
                            'information' => __('Here you can configure whether you want to include other shops reviews.'),
                            'options'     => ReviewValidationScopeEnum::selectOptions(),
                            'value'       => $this->loadReviewValidationScopes($shop),
                        ],
                    ],
                ]
            ],
            'args'      => [
                'updateRoute' => [
                    'name'       => 'grp.models.org.shop.update',
                    'parameters' => [
                        'organisation' => $shop->organisation_id,
                        'shop'         => $shop->id,
                    ],
                ],
            ],
        ];

        if ($isExternal) {
            if (!isset($formData['blueprint'])) {
                $formData['blueprint'] = [];
            }

            $filteredBlueprint = [];

            foreach ($formData['blueprint'] as $section) {
                if (
                    is_array($section)
                    && isset($section['label'])
                    && is_string($section['label'])
                    && in_array($section['label'], $allowedBlueprintLabels, true)
                ) {
                    $filteredBlueprint[] = $section;
                }
            }

            $formData['blueprint'] = array_values($filteredBlueprint);
        }

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

                'formData' => $formData,

            ]
        );
    }

    private function loadReviewRatingLabels(Shop $shop): array
    {
        $stored = ReviewRatingLabel::query()
            ->where('model_type', 'shop')
            ->where('model_id', $shop->id)
            ->get()
            ->groupBy(fn ($item) => $item->review_context instanceof ReviewContextEnum
                ? $item->review_context->value
                : (string)$item->review_context)
            ->map(fn ($items) => $items->mapWithKeys(fn ($item) => [
                $item->dimension instanceof ReviewRatingDimensionEnum
                    ? $item->dimension->value
                    : (string)$item->dimension => $item->label,
            ])->all())
            ->all();

        $emptyDimensions = array_fill_keys(ReviewRatingDimensionEnum::values(), '');

        $tabLabels = $shop->getCustomReviewCategoryLabel();
        $reviewLabel = collect(ReviewContextEnum::values())
            ->mapWithKeys(fn (string $context) => [
                $context => [
                    ...$emptyDimensions,
                    ...($stored[$context] ?? []),
                    'label_tab' => data_get($tabLabels, $context)
                ],
            ])
            ->all();

        return $reviewLabel;
    }

    /**
     * @return array<int, array{context: string, label: string, enabled: bool, scope: string}>
     */
    private function loadReviewValidationScopes(Shop $shop): array
    {
        $tabLabels = $shop->getCustomReviewCategoryLabel();

        $contexts = [
            ReviewContextEnum::SHOP->value,
            ReviewContextEnum::FAMILY->value,
            ReviewContextEnum::PRODUCT->value,
        ];

        return collect($contexts)
            ->map(fn (string $context) => [
                'context' => $context,
                'label'   => data_get($tabLabels, $context, Arr::get(ReviewContextEnum::shortLabels(), $context, $context)),
                'enabled' => (bool)Arr::get($shop->settings, "reviews.validation_scope.$context.enabled", false),
                'scope'   => Arr::get($shop->settings, "reviews.validation_scope.$context.scope", ReviewValidationScopeEnum::ORGANISATION->value),
            ])
            ->all();
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
