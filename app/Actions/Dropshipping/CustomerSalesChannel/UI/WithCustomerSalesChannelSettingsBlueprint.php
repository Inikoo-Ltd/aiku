<?php

namespace App\Actions\Dropshipping\CustomerSalesChannel\UI;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Helpers\TaxCategory;
use Illuminate\Support\Arr;
use Spatie\LaravelOptions\Options;

trait WithCustomerSalesChannelSettingsBlueprint
{
    /**
     * @return array<int, array{label: string, icon: string, fields: array<string, array<string, mixed>>}>
     */
    public function getCustomerSalesChannelSettingsBlueprint(CustomerSalesChannel $customerSalesChannel): array
    {
        /** @var \App\Models\Dropshipping\EbayUser|\App\Models\Dropshipping\WooCommerceUser|\App\Models\Dropshipping\ShopifyUser $user */
        $user = $customerSalesChannel->user;

        $properties = [
            [
                "label" => __("Pricing"),
                'icon' => 'fa-light fa-user',
                'fields' => [
                    'is_vat_adjustment' => [
                        'type' => 'toggle',
                        'label' => __('Show prices with VAT'),
                        'information' => __('Display only: when on, the prices we show you in your product list include VAT at the rate of the category below. It does not change the prices we send to your sales channel.'),
                        'value' => (bool)Arr::get($customerSalesChannel->settings, 'tax_category.checked')
                    ],
                    'tax_category_id' => [
                        'type' => 'select',
                        'label' => __('Vat Category'),
                        'information' => __('The VAT rate used to show prices including VAT.'),
                        'required' => true,
                        'hidden' => !Arr::get($customerSalesChannel->settings, 'tax_category.checked'),
                        'options' => Options::forModels(TaxCategory::class),
                        'value' => Arr::get($customerSalesChannel->settings, 'tax_category.id')
                    ],
                    'pricing_type' => [
                        'type' => 'pricing_policy',
                        'label' => __('Pricing Policy'),
                        'information' => __('Products you add to this channel are priced from their RRP with this rule. Changing it does not reprice products already in the channel: use Edit Price in My Products for those.'),
                        'value' => Arr::get($customerSalesChannel->settings, 'pricing.type') ?: 'percent',
                        'currency_code' => $customerSalesChannel->shop->currency->code,
                        'currency_symbol' => $customerSalesChannel->shop->currency->symbol ?? $customerSalesChannel->shop->currency->code,
                        'example_price' => 10,
                        'value_field' => 'pricing_value',
                        'applies_to_new_products_only' => true,
                        'hasOther' => [
                            [
                                'name'  => 'pricing_value',
                                'value' => Arr::get($customerSalesChannel->settings, 'pricing.value') ?? 0
                            ]
                        ]
                    ]
                ]
            ],
        ];

        if ($user instanceof WooCommerceUser) {
            $properties[] = [
                "label" => __("Orders"),
                'icon' => 'fa-light fa-truck',
                'fields' => [
                    'private_tracking_notes' => [
                        'type' => 'toggle',
                        'label' => __('Private tracking notes'),
                        'information' => __('When an order is dispatched we add its tracking to the order in WooCommerce as a note. Off: the note is for the customer, and WooCommerce emails it to them. On: the note is private, only you see it, and WooCommerce does not email it to your customer. Either way the order is completed with its tracking, and WooCommerce still sends its own order completed email if you have it switched on.'),
                        'value' => (bool) Arr::get($customerSalesChannel->settings, 'woocommerce.private_tracking_notes')
                    ],
                ]
            ];
        }

        if ($user instanceof EbayUser) {
            $pricingPolicy = Arr::get($customerSalesChannel->settings, 'do_not_update_prices')
                ? 'not_follow'
                : (Arr::get($customerSalesChannel->settings, 'pricing.type') ?: 'percent');

            $properties[0]['fields'] = [
                'is_vat_adjustment' => $properties[0]['fields']['is_vat_adjustment'],
                'tax_category_id'   => $properties[0]['fields']['tax_category_id'],
                'pricing_type' => [
                    'type' => 'pricing_policy',
                    'label' => __('Pricing Policy'),
                    'information' => __('The default for every product in this channel, so you never have to price them one by one. A single product can still be given its own price in Edit Product. Choose "Do not follow RRP" to manage prices yourself on eBay: we will never upload or overwrite them.'),
                    'value' => $pricingPolicy,
                    'currency_code' => $customerSalesChannel->shop->currency->code,
                    'currency_symbol' => $customerSalesChannel->shop->currency->symbol ?? $customerSalesChannel->shop->currency->code,
                    'example_price' => 10,
                    'value_field' => 'pricing_value',
                    'hasOther' => [
                        [
                            'name'  => 'pricing_value',
                            'value' => Arr::get($customerSalesChannel->settings, 'pricing.value') ?? 0
                        ],
                        [
                            'name'  => 'pricing_reset_all',
                            'value' => false
                        ]
                    ],
                    'saveConfirmation' => [
                        'whenValueIs' => ['percent', 'fixed'],
                        'title'       => __('Reprice every product?'),
                        'description' => __('Saving reprices all :count products in this channel from their RRP and uploads the new prices to eBay. Products where you set your own price are not touched.', [
                            'count' => $customerSalesChannel->portfolios()->where('status', true)->count()
                        ]),
                        'yesLabel'    => __('Save and reprice')
                    ]
                ],
                'upload_as_draft' => [
                    'type' => 'toggle',
                    'label' => __('Upload as draft'),
                    'information' => __('When on, new products are created on eBay unpublished so you can review the price and text before they go live. Publish them from your product list when you are ready.').' '.__('Drafts do not show in eBay Seller Hub until they are published.'),
                    'value' => (bool) Arr::get($customerSalesChannel->settings, 'upload_as_draft')
                ],
            ];
            $properties = [
                ...$properties,
                [
                    "label" => __("Shipping"),
                    'icon' => 'fa-light fa-truck',
                    'fields' => [
                        'shipping_service' => [
                            'type' => 'select',
                            'label' => __('Shipping service'),
                            'options' => Options::forArray($user->getServicesForOptions()),
                            'value' => Arr::get($customerSalesChannel->settings, 'shipping.service_code'),
                        ],
                        'shipping_price' => [
                            'type' => 'input',
                            'label' => __('Shipping price'),
                            'value' => Arr::get($customerSalesChannel->settings, 'shipping.price')
                        ],
                        'shipping_max_dispatch_time' => [
                            'type' => 'input',
                            'label' => __('Shipping max dispatch time'),
                            'value' => Arr::get($customerSalesChannel->settings, 'shipping.max_dispatch_time')
                        ],
                    ]
                ],
                [
                    "label" => __("Returns"),
                    'icon' => 'fa-light fa-arrow-left',
                    'fields' => [
                        'return_accepted' => [
                            'type' => 'toggle',
                            'label' => __('Returns Accepted'),
                            'value' => (bool)Arr::get($customerSalesChannel->settings, 'return.accepted')
                        ],
                        'return_payer' => [
                            'type' => 'select',
                            'label' => __('Return Payer'),
                            'required' => true,
                            'hidden' => !Arr::get($customerSalesChannel->settings, 'return.accepted'),
                            'options' => Options::forArray([
                                'SELLER' => __('Seller'),
                                'BUYER' => __('Buyer')
                            ]),
                            'value' => Arr::get($customerSalesChannel->settings, 'return.payer')
                        ],
                        'return_within' => [
                            'type' => 'select',
                            'label' => __('Returns within'),
                            'required' => true,
                            'hidden' => !Arr::get($customerSalesChannel->settings, 'return.accepted'),
                            'options' => Options::forArray([
                                14 => __('14 Days'),
                                30 => __('30 Days'),
                                60 => __('60 Days')
                            ]),
                            'value' => Arr::get($customerSalesChannel->settings, 'return.within')
                        ],
                        'return_description' => [
                            'type' => 'textarea',
                            'label' => __('Return description'),
                            'hidden' => !Arr::get($customerSalesChannel->settings, 'return.accepted'),
                            'value' => Arr::get($customerSalesChannel->settings, 'return.description')
                        ],
                    ]
                ],
            ];
        }

        return [
            [
                "label" => __("Properties"),
                'icon' => 'fa-light fa-fingerprint',
                'fields' => [
                    'name' => [
                        'type' => 'input',
                        'label' => __('Store name'),
                        'value' => $customerSalesChannel->name
                    ],
                ]
            ],
            [
                "label" => __("Manage Stock"),
                'icon' => 'fa-light fa-box',
                'fields' => [
                    'stock_update' => [
                        'type' => 'toggle',
                        'label' => __('Stock Update'),
                        'information' => __('When enabled, we automatically sync your stock levels to this channel. Max Quantity To Advertise caps the stock figure shown on the channel, and Stock Threshold hides a product once its stock falls to that level.'),
                        'value' => (bool)$customerSalesChannel->stock_update
                    ],
                    'max_quantity_advertise' => [
                        'type' => 'input',
                        'label' => __('Max Quantity To Advertise'),
                        'information' => __('The maximum stock quantity advertised on the channel, even if more is available. Leave empty for no cap.'),
                        'placeholder' => __('No cap'),
                        'hidden' => !$customerSalesChannel->stock_update,
                        'value' => $customerSalesChannel->max_quantity_advertise ?: null
                    ],
                    'stock_threshold' => [
                        'type' => 'input',
                        'label' => __('Stock Threshold'),
                        'information' => __('When stock falls to this level, the product is advertised as out of stock on the channel. Leave empty to always advertise real stock.'),
                        'placeholder' => __('No threshold'),
                        'hidden' => !$customerSalesChannel->stock_update,
                        'value' => $customerSalesChannel->stock_threshold ?: null
                    ],
                ]
            ],
            ...$properties
        ];
    }

    /**
     * @return array<int, array{label: string, icon: string, fields: array<string, array<string, mixed>>}>
     */
    public function getReadOnlyCustomerSalesChannelSettingsBlueprint(CustomerSalesChannel $customerSalesChannel): array
    {
        return array_map(function (array $section) {
            $section['fields'] = array_map(function (array $field) {
                unset($field['saveConfirmation']);

                return [
                    ...$field,
                    'readonly'     => true,
                    'disabled'     => true,
                    'noSaveButton' => true,
                ];
            }, $section['fields']);

            return $section;
        }, $this->getCustomerSalesChannelSettingsBlueprint($customerSalesChannel));
    }
}
