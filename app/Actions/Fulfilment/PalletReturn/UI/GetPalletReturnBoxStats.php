<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Apr 2025 13:41:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn\UI;

use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Http\Resources\Fulfilment\FulfilmentCustomerResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPalletReturnBoxStats
{
    use AsObject;

    public function handle(PalletReturn $palletReturn, FulfilmentCustomer|Fulfilment $parent, bool $fromRetina = false): array
    {
        $showGrossAndDiscount = $palletReturn->gross_amount !== $palletReturn->net_amount;

        return [
            'collection_notes'    => $palletReturn->collection_notes ?? '',
            'recurring_bill'      => $this->getRecurringBillData($palletReturn, $parent),
            'invoice'             => $this->getInvoiceData($palletReturn, $parent),
            'fulfilment_customer' => array_merge(
                FulfilmentCustomerResource::make($palletReturn->fulfilmentCustomer)->getArray(),
                GetPalletReturnAddressManagement::make()->boxStatsAddressData(palletReturn: $palletReturn, forRetina: $fromRetina)
            ),
            'is_platform_address' => !blank($palletReturn->platform_id),
            'platform' => $palletReturn->platform ? [
                'id' => $palletReturn->platform->id,
                'code' => $palletReturn->platform->code,
                'slug' => $palletReturn->platform->slug,
                'name' => $palletReturn->platform->name
            ] : null,
            'parcels'   => $palletReturn->parcels,
            'platform_customer' => Arr::get($palletReturn->data, 'destination'),
            'delivery_state'      => PalletReturnStateEnum::stateIcon()[$palletReturn->state->value],
            'order_summary'       => [
                [
                    [
                        'label'       => __('Services'),
                        'quantity'    => $palletReturn->stats->number_services ?? 0,
                        'price_base'  => '',
                        'price_total' => $palletReturn->services_amount
                    ],
                    [
                        'label'       => __('Physical Goods'),
                        'quantity'    => $palletReturn->stats->number_physical_goods ?? 0,
                        'price_base'  => '',
                        'price_total' => $palletReturn->goods_amount
                    ],

                ],
                $showGrossAndDiscount ? [
                    [
                        'label'       => __('Gross'),
                        'information' => '',
                        'price_total' => $palletReturn->gross_amount
                    ],
                    [
                        'label'       => __('Discounts'),
                        'information' => '',
                        'price_total' => $palletReturn->discount_amount
                    ],
                ] : [],
                [
                    [
                        'label'       => __('Net'),
                        'information' => '',
                        'price_total' => $palletReturn->net_amount
                    ],
                    [
                        'label'       => __('Tax'),
                        'information' => '',
                        'price_total' => $palletReturn->tax_amount
                    ],
                ],
                [
                    [
                        'label'       => __('Total'),
                        'price_total' => $palletReturn->total_amount
                    ],
                ],
                'currency' => CurrencyResource::make($palletReturn->currency),
                // 'currency_code'                => 'usd',  // TODO
                // 'number_pallets'               => $palletReturn->stats->number_pallets,
                // 'number_services'              => $palletReturn->stats->number_services,
                // 'number_physical_goods'        => $palletReturn->stats->number_physical_goods,
                // 'pallets_price'                => 0,  // TODO
                // 'physical_goods_price'         => 0,  // TODO
                // 'services_price'               => 0,  // TODO
                // 'total_pallets_price'          => 0,  // TODO
                // 'total_services_price'         => $palletReturn->stats->total_services_price,
                // 'total_physical_goods_price'   => $palletReturn->stats->total_physical_goods_price,
                // 'shipping'                     => [
                //     'tooltip'           => __('Shipping fee to your address using DHL service.'),
                //     'fee'               => 11111, // TODO
                // ],
                // 'tax'                      => [
                //     'tooltip'           => __('Tax is based on 10% of total order.'),
                //     'fee'               => 99999, // TODO
                // ],
                // 'total_price'                  => $palletReturn->stats->total_price
            ]
        ];
    }

    public function getRecurringBillData(PalletReturn $palletReturn, FulfilmentCustomer|Fulfilment $parent): ?array
    {
        $recurringBillData = null;
        if ($palletReturn->recurringBill) {
            $recurringBill = $palletReturn->recurringBill;

            if ($parent instanceof Fulfilment) {
                $route = [
                    'name'       => 'grp.org.fulfilments.show.operations.recurring_bills.current.show',
                    'parameters' => [
                        'organisation'  => $recurringBill->organisation->slug,
                        'fulfilment'    => $parent->slug,
                        'recurringBill' => $recurringBill->slug
                    ]
                ];
            } else { //FulfilmentCustomer
                $route = [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.recurring_bills.show',
                    'parameters' => [
                        'organisation'       => $recurringBill->organisation->slug,
                        'fulfilment'         => $parent->fulfilment->slug,
                        'fulfilmentCustomer' => $parent->slug,
                        'recurringBill'      => $recurringBill->slug
                    ]
                ];
            }
            $recurringBillData = [
                'reference'    => $recurringBill->reference,
                'status'       => $recurringBill->status,
                'total_amount' => $recurringBill->total_amount,
                'route'        => $route
            ];
        }

        return $recurringBillData;
    }

    public function getInvoiceData(PalletReturn $palletReturn, FulfilmentCustomer|Fulfilment $parent): ?array
    {
        $invoiceData = null;
        if ($palletReturn->recurringBill) {
            $recurringBill = $palletReturn->recurringBill;
            if ($recurringBill->invoices) {
                $invoice = $recurringBill->invoices;
                if ($parent instanceof Fulfilment) {
                    $route = [
                        'name'       => 'grp.org.fulfilments.show.operations.invoices.show',
                        'parameters' => [
                            'organisation'  => $recurringBill->organisation->slug,
                            'fulfilment'    => $parent->slug,
                            'invoice' => $recurringBill->invoices->slug
                        ]
                    ];
                } else { //FulfilmentCustomer
                    $route = [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.invoices.show',
                        'parameters' => [
                            'organisation'  => $recurringBill->organisation->slug,
                            'fulfilment'    => $parent->fulfilment->slug,
                            'fulfilmentCustomer' => $parent->slug,
                            'invoice' => $invoice->slug
                        ]
                    ];
                }
                $invoiceData = [
                    'reference'    => $invoice->reference,
                    'status'       => $invoice->pay_status,
                    'total_amount' => $invoice->total_amount,
                    'route'        => $route
                ];
            }
        }

        return $invoiceData;
    }

}
