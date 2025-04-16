<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Apr 2025 17:54:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\RecurringBillTransaction;
use App\Models\Fulfilment\Space;
use Carbon\Carbon;
use Illuminate\Support\Arr;

trait WithInvoiceTransactionFulfilmentExtraData
{
    public function getServicePalletInfo(array $data, bool $isRetina): ?array
    {
        $palletId     = Arr::get($data, 'pallet_id');
        $handlingDate = Arr::get($data, 'date');


        $pallet = null;
        if ($palletId) {
            $pallet = Pallet::find($palletId);
        }
        if (!$pallet) {
            return null;
        }


        $palletReference = $pallet->reference;
        $palletRoute     = $isRetina ?
            [
                'name'       => 'retina.fulfilment.storage.pallets.show',
                'parameters' => [
                    'pallet' => $pallet->slug
                ]

            ] : [
            'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.show',
            'parameters' => [
                'organisation'       => $pallet->organisation->slug,
                'fulfilment'         => $pallet->fulfilment->slug,
                'fulfilmentCustomer' => $pallet->fulfilmentCustomer->slug,
                'pallet'             => $pallet->slug
            ]
        ];

        $servicePalletInfo = [
            'palletReference' => $palletReference,
            'palletRoute'     => $palletRoute,

        ];

        if ($handlingDate) {
            $servicePalletInfo['handlingDate'] = Carbon::parse($handlingDate)->format('d M Y');
        }


        return $servicePalletInfo;
    }


    public function getRentedScopeInfo(?int $recurring_bill_transaction_id, ?string $modelType, ?int $modelId, bool $isRetina): ?array
    {

        $rentalObjectInfo         = null;
        if (!$recurring_bill_transaction_id || !$modelType) {
            return null;
        }


        $recurringBillTransaction = RecurringBillTransaction::find($recurring_bill_transaction_id);

        if ($recurringBillTransaction) {
            $rentalObjectInfo = [
                'model'       => '',
                'title'       => '',
                'route'       => '',
                'after_title' => null,
            ];

            if ($modelType == 'Rental') {
                $rentalObjectInfo = $this->getRentalInfo($rentalObjectInfo, $recurringBillTransaction, $isRetina);
            } elseif ($recurringBillTransaction->pallet_delivery_id) {
                $rentalObjectInfo = $this->getPalletDeliveryInfo($rentalObjectInfo, $recurringBillTransaction, $isRetina);
            } elseif ($recurringBillTransaction->pallet_return_id) {
                $rentalObjectInfo = $this->getPalletReturnInfo($rentalObjectInfo, $recurringBillTransaction, $isRetina);
            } elseif ($modelType === 'Space') {
                $rentalObjectInfo = $this->getSpaceInfo($rentalObjectInfo, $modelId, $isRetina);
            }
        }


        return $rentalObjectInfo;
    }

    protected function getSpaceInfo(array $rentalObjectInfo, $modelId, bool $isRetina): ?array
    {
        $space = Space::find($modelId);
        if ($space) {
            $desc_model       = __('Space (parking)');
            $desc_title       = $space->reference;
            $desc_route       = $isRetina
                ? []
                : [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.spaces.show',
                    'parameters' => [
                        'organisation'       => $space->organisation,
                        'fulfilment'         => $space->fulfilment,
                        'fulfilmentCustomer' => $space->fulfilmentCustomer->slug,
                        'space'              => $space->slug
                    ]
                ];
            $rentalObjectInfo = [
                'model'       => $desc_model,
                'title'       => $desc_title,
                'route'       => $desc_route,
                'after_title' => null
            ];
        }

        return $rentalObjectInfo;
    }

    protected function getPalletReturnInfo(array $rentalObjectInfo, RecurringBillTransaction $recurringBillTransaction, bool $isRetina): ?array
    {
        $palletReturn = PalletReturn::find($recurringBillTransaction->pallet_return_id);
        if ($palletReturn) {
            $desc_title = $palletReturn->reference;
            $desc_model = __('Pallet Return');
            $desc_route = $isRetina
                ? [
                    'name'       => 'retina.fulfilment.storage.pallet_returns.show',
                    'parameters' => [
                        'palletReturn' => $palletReturn->slug
                    ]
                ]
                : [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.show',
                    'parameters' => [
                        'organisation'       => $palletReturn->organisation,
                        'fulfilment'         => $palletReturn->fulfilment,
                        'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->slug,
                        'palletReturn'       => $palletReturn->slug
                    ]
                ];

            $rentalObjectInfo = [
                'model'       => $desc_model,
                'title'       => $desc_title,
                'route'       => $desc_route,
                'after_title' => null
            ];
        }

        return $rentalObjectInfo;
    }


    protected function getPalletDeliveryInfo(array $rentalObjectInfo, RecurringBillTransaction $recurringBillTransaction, bool $isRetina): ?array
    {
        $palletDelivery = PalletDelivery::find($recurringBillTransaction->pallet_delivery_id);
        if ($palletDelivery) {
            $desc_title = $palletDelivery->reference;
            $desc_model = __('Pallet Delivery');
            $desc_route = $isRetina
                ? [
                    'name'       => 'retina.fulfilment.storage.pallet_deliveries.show',
                    'parameters' => [
                        'palletDelivery' => $palletDelivery->slug
                    ]
                ]
                : [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.show',
                    'parameters' => [
                        'organisation'       => $palletDelivery->organisation,
                        'fulfilment'         => $palletDelivery->fulfilment,
                        'fulfilmentCustomer' => $palletDelivery->fulfilmentCustomer->slug,
                        'palletDelivery'     => $palletDelivery->slug
                    ]
                ];

            $rentalObjectInfo = [
                'model'       => $desc_model,
                'title'       => $desc_title,
                'route'       => $desc_route,
                'after_title' => null
            ];
        }

        return $rentalObjectInfo;
    }

    protected function getRentalInfo(array $rentalObjectInfo, RecurringBillTransaction $recurringBillTransaction, bool $isRetina): ?array
    {
        $pallet = Pallet::find($recurringBillTransaction->item_id);
        if ($pallet) {
            $desc_title       = $pallet->customer_reference;
            $desc_model       = __('Storage');
            $desc_after_title = $pallet->reference;
            $desc_route       = $isRetina
                ?
                [
                    'name'       => 'retina.fulfilment.storage.pallets.show',
                    'parameters' => [
                        'pallet' => $pallet->slug
                    ]
                ]
                : [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.show',
                    'parameters' => [
                        'organisation'       => $pallet->organisation,
                        'fulfilment'         => $pallet->fulfilment,
                        'fulfilmentCustomer' => $pallet->fulfilmentCustomer->slug,
                        'pallet'             => $pallet->slug
                    ]
                ];
            $rentalObjectInfo = [
                'model'       => $desc_model,
                'title'       => $desc_title,
                'route'       => $desc_route,
                'after_title' => $desc_after_title,
            ];
        }

        return $rentalObjectInfo;
    }

}
