<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Jul 2024 19:55:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentTransaction;

use App\Actions\Fulfilment\PalletDelivery\Hydrators\PalletDeliveryHydrateTransactions;
use App\Actions\Fulfilment\PalletReturn\Hydrators\PalletReturnHydrateTransactions;
use App\Actions\Fulfilment\RecurringBillTransaction\StoreRecurringBillTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\FulfilmentTransaction\FulfilmentTransactionTypeEnum;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\FulfilmentTransaction;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreFulfilmentTransaction extends OrgAction
{
    use WithActionUpdate;

    public function handle(PalletDelivery|PalletReturn $parent, array $modelData): FulfilmentTransaction
    {
        data_set($modelData, 'tax_category_id', $parent->tax_category_id, overwrite: false);

        $historicAsset = HistoricAsset::find($modelData['historic_asset_id']);
        $net           = $modelData['quantity'] * $historicAsset->asset->price;

        data_set($modelData, 'organisation_id', $parent->organisation_id);
        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'fulfilment_id', $parent->fulfilment_id);
        data_set($modelData, 'fulfilment_customer_id', $parent->fulfilment_customer_id);
        data_set($modelData, 'historic_asset_id', $historicAsset->id);
        data_set($modelData, 'asset_id', $historicAsset->asset_id);
        data_set($modelData, 'gross_amount', $net);
        data_set($modelData, 'net_amount', $net);

        if ($historicAsset->model_type === 'Product') {
            data_set($modelData, 'type', FulfilmentTransactionTypeEnum::PRODUCT);
        } else {
            data_set($modelData, 'type', FulfilmentTransactionTypeEnum::SERVICE);
        }

        /** @var FulfilmentTransaction $fulfilmentTransaction */
        $fulfilmentTransaction = $parent->transactions()->create($modelData);

        if ($parent->recurringBill && $parent->recurringBill->status == RecurringBillStatusEnum::CURRENT) {
            $recurringBillTransactionData = [
                'start_date'                => now(),
                'quantity'                  => $fulfilmentTransaction->quantity,
                'fulfilment_transaction_id' => $fulfilmentTransaction->id

            ];

            if ($parent instanceof PalletDelivery) {
                $recurringBillTransactionData['pallet_delivery_id'] = $parent->id;
            } else {
                $recurringBillTransactionData['pallet_return_id'] = $parent->id;
            }


            StoreRecurringBillTransaction::make()->action($parent->recurringBill, $fulfilmentTransaction, $recurringBillTransactionData);
        }



        if ($fulfilmentTransaction->parent_type == 'PalletDelivery') {
            PalletDeliveryHydrateTransactions::run($fulfilmentTransaction->parent);
        } else {
            PalletReturnHydrateTransactions::run($fulfilmentTransaction->parent);
        }

        $fulfilmentTransaction->refresh();
        $this->update(
            $fulfilmentTransaction,
            [
                'grp_net_amount' => $fulfilmentTransaction->net_amount * $fulfilmentTransaction->grp_exchange,
                'org_net_amount' => $fulfilmentTransaction->net_amount * $fulfilmentTransaction->org_exchange
            ]
        );
        $fulfilmentTransaction->refresh();
        SetClausesInFulfilmentTransaction::run($fulfilmentTransaction);

        return $fulfilmentTransaction;
    }

    public function rules(): array
    {
        return [
            'is_auto_assign'    => ['sometimes', 'boolean'],
            'quantity'          => ['required', 'numeric', 'gt:0'],
            'historic_asset_id' => [
                'required',
                Rule::Exists('historic_assets', 'id')
                    ->where('organisation_id', $this->organisation->id)
            ]
        ];
    }


    public function inPalletDelivery(PalletDelivery $palletDelivery, ActionRequest $actionRequest): void
    {
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $actionRequest);

        $this->handle($palletDelivery, $this->validatedData);
    }

    public function inPalletReturn(PalletReturn $palletReturn, ActionRequest $actionRequest): void
    {
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $actionRequest);
        $this->handle($palletReturn, $this->validatedData);
    }


    public function action(PalletDelivery|PalletReturn $parent, array $modelData): FulfilmentTransaction
    {
        $this->asAction = true;

        $this->initialisationFromFulfilment($parent->fulfilment, $modelData);

        return $this->handle($parent, $this->validatedData);
    }


}
