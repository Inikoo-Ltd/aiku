<?php

/*
 * author Arya Permana - Kirin
 * created on 20-03-2025-16h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Accounting;

use App\Models\Billables\Service;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\RecurringBillTransaction;
use App\Models\Fulfilment\Space;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $quantity
 * @property string $net_amount
 * @property string $name
 * @property string $currency_code
 * @property mixed $id
 * @property mixed $in_process
 * @property mixed $model_type
 * @property mixed $model_id
 * @property mixed $recurring_bill_transaction_id
 * @property mixed $data
 */
class ItemizedInvoiceTransactionsResource extends JsonResource
{
    public function toArray($request): array
    {
        $desc_model       = '';
        $desc_title       = '';
        $desc_after_title = '';
        $desc_route       = null;

        $recurringBillTransaction = RecurringBillTransaction::find($this->recurring_bill_transaction_id);
        if ($recurringBillTransaction) {
            if ($this->model_type == 'Rental') {
                $pallet = Pallet::find($recurringBillTransaction->item_id);
                if ($pallet) {
                    $desc_title       = $pallet->customer_reference;
                    $desc_model       = __('Storage');
                    $desc_after_title = $pallet->reference;
                    $desc_route       = match (request()->routeIs('retina.*')) {
                        true => [
                            'name'       => 'retina.fulfilment.storage.pallets.show',
                            'parameters' => [
                                'pallet' => $pallet->slug
                            ]
                        ],
                        default => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.show',
                            'parameters' => [
                                'organisation'       => $pallet->organisation,
                                'fulfilment'         => $pallet->fulfilment,
                                'fulfilmentCustomer' => $pallet->fulfilmentCustomer->slug,
                                'pallet'             => $pallet->slug
                            ]
                        ]
                    };
                }
            } elseif ($recurringBillTransaction->pallet_delivery_id) {
                $palletDelivery = PalletDelivery::find($recurringBillTransaction->pallet_delivery_id);
                if ($palletDelivery) {
                    $desc_title = $palletDelivery->reference;
                    $desc_model = __('Pallet Delivery');
                    $desc_route = match (request()->routeIs('retina.*')) {
                        true => [
                            'name'       => 'retina.fulfilment.storage.pallet_deliveries.show',
                            'parameters' => [
                                'palletDelivery' => $palletDelivery->slug
                            ]
                        ],
                        default => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.show',
                            'parameters' => [
                                'organisation'       => $palletDelivery->organisation,
                                'fulfilment'         => $palletDelivery->fulfilment,
                                'fulfilmentCustomer' => $palletDelivery->fulfilmentCustomer->slug,
                                'palletDelivery'     => $palletDelivery->slug
                            ]
                        ]
                    };
                }
            } elseif ($recurringBillTransaction->pallet_return_id) {
                $palletReturn = PalletReturn::find($recurringBillTransaction->pallet_return_id);
                if ($palletReturn) {
                    $desc_title = $palletReturn->reference;
                    $desc_model = __('Pallet Return');
                    $desc_route = match (request()->routeIs('retina.*')) {
                        true => [
                            'name'       => 'retina.fulfilment.storage.pallet_returns.show',
                            'parameters' => [
                                'palletReturn' => $palletReturn->slug
                            ]
                        ],
                        default => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.show',
                            'parameters' => [
                                'organisation'       => $palletReturn->organisation,
                                'fulfilment'         => $palletReturn->fulfilment,
                                'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->slug,
                                'palletReturn'       => $palletReturn->slug
                            ]
                        ]
                    };
                }
            } elseif ($this->model_type === 'Space') {
                $space = Space::find($this->model_id);
                if ($space) {
                    $desc_model = __('Space (parking)');
                    $desc_title = $space->reference;
                    $desc_route = match (request()->routeIs('retina.*')) {
                        true => [],
                        default => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.spaces.show',
                            'parameters' => [
                                'organisation'       => $request->route()->originalParameters()['organisation'],
                                'fulfilment'         => $request->route()->originalParameters()['fulfilment'],
                                'fulfilmentCustomer' => $space->fulfilmentCustomer->slug,
                                'space'              => $space->slug
                            ]
                        ]
                    };
                }
            }
        }
        if ($this->model_type == 'Service') {
            $service = Service::find($this->model_id);
            if ($service->is_pallet_handling) {
                $pallet           = Pallet::find($this->data['pallet_id']);
                $desc_title       = $pallet->customer_reference;
                $desc_after_title = $pallet->reference.' - '.Carbon::parse($this->data['date'])->format('d M Y');
                $desc_model       = __('Pallet Handling');
                $desc_route       = match (request()->routeIs('retina.*')) {
                    true => [
                        'name'       => 'retina.fulfilment.storage.pallets.show',
                        'parameters' => [
                            'pallet' => $pallet->slug
                        ]
                    ],
                    default => [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.show',
                        'parameters' => [
                            'organisation'       => $request->route()->originalParameters()['organisation'],
                            'fulfilment'         => $request->route()->originalParameters()['fulfilment'],
                            'fulfilmentCustomer' => $pallet->fulfilmentCustomer->slug,
                            'pallet'             => $pallet->slug
                        ]
                    ]
                };
            }
        }

        return [
            'type'          => $this->model_type,
            'code'          => $this->code,
            'name'          => $this->name,
            'description'   => [
                'model'       => $desc_model,
                'title'       => $desc_title,
                'route'       => $desc_route,
                'after_title' => $desc_after_title,
            ],
            'quantity'      => (int)$this->quantity,
            'net_amount'    => $this->net_amount,
            'currency_code' => $this->currency_code,
            'in_process'    => $this->in_process,
        ];
    }
}
