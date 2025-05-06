<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Feb 2024 19:57:44 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer\UI;

use App\Actions\CRM\Customer\UI\GetCustomerAddressManagement;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Http\Resources\Fulfilment\FulfilmentCustomerResource;
use App\Http\Resources\Catalogue\RentalAgreementResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetFulfilmentCustomerShowcase
{
    use AsObject;

    public function handle(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): array
    {
        $irisDomain = $fulfilmentCustomer->fulfilment->shop?->website?->domain;

        $recurringBillData = null;

        $webUser = $fulfilmentCustomer->customer->webUsers()->first();
        $webUserRoute = null;
        if($webUser) {
            $webUserRoute = [
                'name'       => 'grp.org.fulfilments.show.crm.customers.show.web-users.edit',
                'parameters' => [
                    'organisation' => $fulfilmentCustomer->organisation->slug,
                    'fulfilment'   => $fulfilmentCustomer->shop->slug,
                    'fulfilmentCustomer'     => $fulfilmentCustomer->slug,
                    'webUser'      => $webUser->slug
                ]
            ];
        }


        if ($fulfilmentCustomer->currentRecurringBill) {
            $recurringBillData = [
                'route'         => [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.recurring_bills.show',
                    'parameters' => [
                        'organisation'       => $fulfilmentCustomer->organisation->slug,
                        'fulfilment'         => $fulfilmentCustomer->fulfilment->slug,
                        'fulfilmentCustomer' => $fulfilmentCustomer->slug,
                        'recurringBill'      => $fulfilmentCustomer->currentRecurringBill->slug,
                    ]
                ],
                'label'         => 'Recurring Bills',
                'start_date'    => $fulfilmentCustomer->currentRecurringBill->start_date ?? '',
                'end_date'      => $fulfilmentCustomer->currentRecurringBill->end_date ?? '',
                'total'         => $fulfilmentCustomer->currentRecurringBill->total_amount ?? '',
                'currency_code' => $fulfilmentCustomer->currentRecurringBill->currency->code ?? '',
                'status'        => $fulfilmentCustomer->currentRecurringBill->status ?? ''
            ];
        }


        /** @noinspection HttpUrlsUsage */
        return [
            'fulfilment_customer' => FulfilmentCustomerResource::make($fulfilmentCustomer)->getArray(),
            'rental_agreement'    => [
                'updated_at'  => $fulfilmentCustomer->rentalAgreement->updated_at ?? null,
                'stats'       => $fulfilmentCustomer->rentalAgreement ? RentalAgreementResource::make($fulfilmentCustomer->rentalAgreement) : false,
                'createRoute' => [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.rental-agreement.create',
                    'parameters' => array_values($request->route()->originalParameters())
                ],
            ],
            'status'              => $fulfilmentCustomer->customer->status,
            'additional_data'     => $fulfilmentCustomer->data,

            'address_management' => GetCustomerAddressManagement::run(customer: $fulfilmentCustomer->customer),

            'currency_code'  => $fulfilmentCustomer->customer->shop->currency->code,
            'balance'        => [
                'current'             => $fulfilmentCustomer->customer->balance,
                'credit_transactions' => $fulfilmentCustomer->customer->stats->number_credit_transactions
            ],
            'recurring_bill' => $recurringBillData,
            'updateRoute'    => [
                'name'       => 'grp.models.fulfilment-customer.update',
                'parameters' => [$fulfilmentCustomer->id]
            ],
            'stats'          => $this->getFulfilmentCustomerStats($fulfilmentCustomer),

            'webhook'            => [
                'webhook_access_key' => $fulfilmentCustomer->webhook_access_key,
                'domain'             => (app()->environment('local') ? 'http://' : 'https://').$irisDomain.'/webhooks/',
                'route'              => [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.webhook.fetch',
                    'parameters' => array_values($request->route()->originalParameters())
                ],
            ],
            'approveRoute'       => [
                'name'       => 'grp.models.customer.approve',
                'parameters' => [
                    'customer' => $fulfilmentCustomer->customer_id
                ]
            ],
            'updateBalanceRoute' => [
                'name'       => 'grp.models.customer_balance.update',
                'parameters' => [
                    'customer' => $fulfilmentCustomer->customer_id
                ]
            ],
            'editWebUser' => $webUserRoute
        ];
    }

    public function getFulfilmentCustomerStats(FulfilmentCustomer $fulfilmentCustomer): array
    {
        $stats = [];


        $stats['pallets'] = [
            'label'       => __('Pallets'),
            'count'       => $fulfilmentCustomer->number_pallets_status_storing,
            'tooltip'     => __('Pallets in warehouse'),
            'description' => __('in warehouse'),

        ];

        foreach (PalletStateEnum::cases() as $case) {
            $stats['pallets']['state'][$case->value] = [
                'value' => $case->value,
                'icon'  => PalletStateEnum::stateIcon()[$case->value],
                'count' => PalletStateEnum::count($fulfilmentCustomer)[$case->value] ?? 0,
                'label' => PalletStateEnum::labels()[$case->value]
            ];
        }

        $stats['pallet_deliveries'] = [
            'label'       => __('Deliveries'),
            'count'       => $fulfilmentCustomer->number_pallet_deliveries,
            'tooltip'     => __('Total number pallet deliveries'),
            'description' => ''
        ];
        foreach (PalletDeliveryStateEnum::cases() as $case) {
            $stats['pallet_delivery']['cases'][$case->value] = [
                'value' => $case->value,
                'icon'  => PalletDeliveryStateEnum::stateIcon()[$case->value],
                'count' => PalletDeliveryStateEnum::count($fulfilmentCustomer)[$case->value],
                'label' => PalletDeliveryStateEnum::labels()[$case->value]
            ];
        }

        $stats['pallet_returns'] = [
            'label' => __('Returns'),
            'count' => $fulfilmentCustomer->number_pallet_returns
        ];
        foreach (PalletReturnStateEnum::cases() as $case) {
            $stats['pallet_return']['cases'][$case->value] = [
                'value' => $case->value,
                'icon'  => PalletReturnStateEnum::stateIcon()[$case->value],
                'count' => PalletReturnStateEnum::count($fulfilmentCustomer)[$case->value],
                'label' => PalletReturnStateEnum::labels()[$case->value]
            ];
        }

        $stats['invoice'] = [
            'label' => __('Invoice'),
            'count' => $fulfilmentCustomer->customer->stats->number_invoices,
            // 'tooltip'     => __('Pallets in warehouse'),
            // 'description' => __('in warehouse'),
        ];

        return $stats;
    }
}
