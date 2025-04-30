<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Apr 2024 09:00:21 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RecurringBillTransaction;

use App\Actions\Fulfilment\RecurringBill\CalculateRecurringBillTotals;
use App\Actions\Fulfilment\RecurringBill\Hydrators\RecurringBillHydrateTransactions;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\FulfilmentTransaction\FulfilmentTransactionTypeEnum;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Fulfilment\FulfilmentTransaction;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Fulfilment\RecurringBillTransaction;
use App\Models\Fulfilment\Space;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StoreRecurringBillTransaction extends OrgAction
{
    private bool $skipHydrators = false;

    public function handle(RecurringBill $recurringBill, Space|Pallet|StoredItem|FulfilmentTransaction|HistoricAsset $item, array $modelData): RecurringBillTransaction
    {
        $handlingPalletId = null;
        if (Arr::exists($modelData, 'pallet_id')) {
            $handlingPalletId = Arr::pull($modelData, 'pallet_id');
        }
        $handlingDate = null;
        if (Arr::exists($modelData, 'handle_date')) {
            $handlingDate = Arr::pull($modelData, 'handle_date');
        }

        data_set($modelData, 'organisation_id', $recurringBill->organisation_id);
        data_set($modelData, 'group_id', $recurringBill->group_id);
        data_set($modelData, 'fulfilment_id', $recurringBill->fulfilment_id);
        data_set($modelData, 'fulfilment_customer_id', $recurringBill->fulfilment_customer_id);
        data_set($modelData, 'tax_category_id', $recurringBill->tax_category_id);


        if ($item instanceof FulfilmentTransaction) {
            if ($item->type == FulfilmentTransactionTypeEnum::SERVICE) {
                $type = 'Service';
            } else {
                $type = 'Product';
            }
            $assetId         = $item->asset->id;
            $historicAssetId = $item->asset->current_historic_asset_id;
            $totalQuantity   = $item->quantity;

            $unitCost = $item->gross_amount / $item->quantity;
            data_set($modelData, 'item_id', $item->historicAsset->model_id);
        } elseif ($item instanceof HistoricAsset) {
            $type            = $item->asset->model_type;
            $assetId         = $item->asset->id;
            $historicAssetId = $item->id;
            $totalQuantity   = Arr::pull($modelData, 'quantity');

            $unitCost = $item->price;
            data_set($modelData, 'item_id', $item->model_id);

            if ($item->model_type == 'Service' && $item->model->is_pallet_handling) {
                if ($handlingPalletId) {
                    data_set($modelData, 'data.handling_service_pallet_id', $handlingPalletId);
                }
                if ($handlingDate) {
                    data_set($modelData, 'data.handling_service_date', $handlingDate);
                }
            }
        } elseif ($item instanceof Space) {
            $type            = 'Space';
            $assetId         = $item->rental->asset_id;
            $historicAssetId = $item->rental->asset->current_historic_asset_id;
            $totalQuantity   = 1;

            $unitCost = $item->rental->price;
            data_set($modelData, 'item_id', $item->id);
        } else {
            $type            = class_basename($item);
            $assetId         = $item->rental->asset_id;
            $historicAssetId = $item->rental->asset->current_historic_asset_id;

            $totalQuantity = 0;

            if ($item instanceof StoredItem) {
                foreach ($item->pallets as $pallet) {
                    $totalQuantity += $pallet->pivot->quantity;
                }
            } else {
                $totalQuantity = 1;
            }

            $unitCost = $item->rental->price;
            data_set($modelData, 'item_id', $item->id);
        }

        data_set($modelData, 'item_type', $type);
        data_set($modelData, 'asset_id', $assetId);
        data_set($modelData, 'historic_asset_id', $historicAssetId);
        data_set($modelData, 'quantity', $totalQuantity);
        data_set($modelData, 'unit_cost', $unitCost);


        /** @var RecurringBillTransaction $recurringBillTransaction */
        $recurringBillTransaction = $recurringBill->transactions()->create($modelData);


        if ($item instanceof Pallet) {
            $item->update(['current_recurring_bill_id' => $recurringBill->id]);
        }
        $recurringBillTransaction = CalculateRecurringBillTransactionDiscountPercentage::make()->action($recurringBillTransaction, $this->hydratorsDelay);
        $recurringBillTransaction = CalculateRecurringBillTransactionTemporalQuantity::make()->action($recurringBillTransaction);
        $recurringBillTransaction = CalculateRecurringBillTransactionAmounts::make()->action($recurringBillTransaction);
        $recurringBillTransaction = CalculateRecurringBillTransactionCurrencyExchangeRates::make()->action($recurringBillTransaction);

        if (!$this->skipHydrators) {
            CalculateRecurringBillTotals::run($recurringBill);
            RecurringBillHydrateTransactions::run($recurringBill);
        }


        return $recurringBillTransaction;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $date = now();
        if (!$this->has('start_date')) {
            $this->set('start_date', $date);
        }
        if (!$this->has('end_date')) {
            $this->set('end_date', $date);
        }
    }

    public function rules(): array
    {
        return [
            'start_date'                => ['required', 'date'],
            'end_date'                  => ['sometimes', 'required', 'date', 'gte:start_date'],
            'quantity'                  => ['sometimes', 'numeric'],
            'pallet_delivery_id'        => ['sometimes', 'exists:pallet_deliveries,id'],
            'pallet_return_id'          => ['sometimes', 'exists:pallet_returns,id'],
            'fulfilment_transaction_id' => ['sometimes', 'exists:fulfilment_transactions,id'],
            'pallet_id'                 => ['sometimes'],
            'handle_date'               => ['sometimes'],
            'data'                      => ['sometimes', 'array'],
        ];
    }

    public function action(RecurringBill $recurringBill, Space|Pallet|StoredItem|FulfilmentTransaction $item, array $modelData, int $hydratorsDelay = 0, bool $skipHydrators = false): RecurringBillTransaction
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->skipHydrators  = $skipHydrators;
        $this->initialisationFromShop($recurringBill->fulfilment->shop, $modelData);

        return $this->handle($recurringBill, $item, $this->validatedData);
    }

    public function asController(RecurringBill $recurringBill, HistoricAsset $historicAsset, ActionRequest $request): void
    {
        $this->initialisationFromFulfilment($recurringBill->fulfilment, $request);

        $this->handle($recurringBill, $historicAsset, $this->validatedData);
    }

}
