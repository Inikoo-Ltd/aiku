<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\UI\Dashboard;

use App\Actions\Fulfilment\FulfilmentCustomer\UI\GetFulfilmentCustomerShowcase;
use App\Http\Resources\Fulfilment\FulfilmentCustomerResource;
use App\Http\Resources\Catalogue\RentalAgreementResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Actions\Helpers\Country\UI\GetAddressData;

class GetRetinaFulfilmentHomeData
{
    use AsObject;

    public function handle(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): array
    {
        $irisDomain = $fulfilmentCustomer->fulfilment->shop?->website?->domain;

        $recurringBillData = null;

        if ($fulfilmentCustomer->currentRecurringBill) {
            $recurringBillData = [
                'route'         => [
                    'name'       => 'retina.fulfilment.billing.next_recurring_bill',
                    'parameters' => []
                ],
                'label'         => 'Recurring Bills',
                'start_date'    => $fulfilmentCustomer->currentRecurringBill->start_date ?? '',
                'end_date'      => $fulfilmentCustomer->currentRecurringBill->end_date ?? '',
                'total'         => $fulfilmentCustomer->currentRecurringBill->total_amount ?? '',
                'currency_code' => $fulfilmentCustomer->currentRecurringBill->currency->code ?? '',
                'status'        => $fulfilmentCustomer->currentRecurringBill->status ?? ''
            ];
        }


        $routeActions = [
            $fulfilmentCustomer->pallets_storage ? [
                'type'        => 'button',
                'style'       => 'create',
                'tooltip'     => __('Book goods into stock, rent storage space, buy packaging material etc'),
                'label'       => __('New Storage or Service'),
                'fullLoading' => true,
                'route'       => [
                    'method'     => 'post',
                    'name'       => 'retina.models.pallet-delivery.store',
                    'parameters' => []
                ]
            ] : false,
            [
                'type'     => 'button',
                'style'    => $fulfilmentCustomer->number_pallets_status_storing ? 'create' : 'gray',
                'disabled' => !$fulfilmentCustomer->number_pallets_status_storing,
                'tooltip'  => $fulfilmentCustomer->number_pallets_status_storing ? __('Make a new dispatch from your stock') : __('This service is available if you have stock to dispatch'),
                'label'    => __('New Dispatch'),
                'route'    => [
                    'method'     => 'post',
                    'name'       => 'retina.models.pallet-return.store',
                    'parameters' => []
                ]
            ],
            [
                'type'    => 'button',
                'style'   => $fulfilmentCustomer->number_pallets_with_stored_items_state_storing ? 'create' : 'gray',
                'disabled' => $fulfilmentCustomer->number_pallets_with_stored_items_state_storing ? false : true,
                'tooltip' => $fulfilmentCustomer->number_pallets_with_stored_items_state_storing ? __('Make a new dispatch from your SKUs') : __('This service is available if you have SKUs to dispatch'),
                'label'   => __('New Dropship Dispatch'),
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'retina.models.pallet-return-stored-items.store',
                    'parameters' => []
                ]
            ]
        ];


        $routeActions = array_filter($routeActions);

        /** @noinspection HttpUrlsUsage */
        return [
            'fulfilment_customer'  => FulfilmentCustomerResource::make($fulfilmentCustomer)->getArray(),
            'rental_agreement'     => [
                'updated_at'  => $fulfilmentCustomer->rentalAgreement->updated_at ?? null,
                'stats'       => $fulfilmentCustomer->rentalAgreement ? RentalAgreementResource::make($fulfilmentCustomer->rentalAgreement) : false,
                'createRoute' => [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.rental-agreement.create',
                    'parameters' => array_values($request->route()->originalParameters())
                ],
            ],
            'status'               => $fulfilmentCustomer->customer->status,
            'additional_data'      => $fulfilmentCustomer->data,
            'address_update_route' => [
                'method'     => 'patch',
                'name'       => 'grp.models.fulfilment-customer.address.update',
                'parameters' => [
                    'fulfilmentCustomer' => $fulfilmentCustomer->id
                ]
            ],
            'route_action'         => $routeActions,
            'addresses'            => [
                'isCannotSelect'              => true,
                // 'address_list'                  => $addressCollection,
                'options'                     => [
                    'countriesAddressData' => GetAddressData::run()
                ],
                'pinned_address_id'           => $fulfilmentCustomer->customer->delivery_address_id,
                'home_address_id'             => $fulfilmentCustomer->customer->address_id,
                'current_selected_address_id' => $fulfilmentCustomer->customer->delivery_address_id,
                // 'selected_delivery_addresses_id' => $palletReturnDeliveryAddressIds,
                'routes_list'                 => [
                    'pinned_route' => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.customer.delivery-address.update',
                        'parameters' => [
                            'customer' => $fulfilmentCustomer->customer_id
                        ]
                    ],
                    'delete_route' => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.fulfilment-customer.delivery-address.delete',
                        'parameters' => [
                            'fulfilmentCustomer' => $fulfilmentCustomer->id
                        ]
                    ],
                    'store_route'  => [
                        'method'     => 'post',
                        'name'       => 'grp.models.fulfilment-customer.address.store',
                        'parameters' => [
                            'fulfilmentCustomer' => $fulfilmentCustomer->id
                        ]
                    ]
                ]
            ],
            'currency_code'        => $fulfilmentCustomer->customer->shop->currency->code,
            'balance'              => [
                'current'             => $fulfilmentCustomer->customer->balance,
                'credit_transactions' => $fulfilmentCustomer->customer->stats->number_credit_transactions
            ],
            'recurring_bill'       => $recurringBillData,
            'updateRoute'          => [
                'name'       => 'grp.models.fulfilment-customer.update',
                'parameters' => [$fulfilmentCustomer->id]
            ],
            'stats'                => GetFulfilmentCustomerShowcase::make()->getFulfilmentCustomerStats($fulfilmentCustomer),

            'webhook'      => [
                'webhook_access_key' => $fulfilmentCustomer->webhook_access_key,
                'domain'             => (app()->environment('local') ? 'http://' : 'https://').$irisDomain.'/webhooks/',
                'route'              => [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.webhook.fetch',
                    'parameters' => array_values($request->route()->originalParameters())
                ],
            ],
            'approveRoute' => [
                'name'       => 'grp.models.customer.approve',
                'parameters' => [
                    'customer' => $fulfilmentCustomer->customer_id
                ]
            ],
        ];
    }

}
