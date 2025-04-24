<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 Jan 2025 00:37:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RecurringBillTransaction;

use App\Actions\Fulfilment\FulfilmentTransaction\UpdateFulfilmentTransaction;
use App\Actions\Fulfilment\RecurringBill\CalculateRecurringBillTotals;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Fulfilment\RecurringBillTransaction;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateRecurringBillTransaction extends OrgAction
{
    use WithActionUpdate;

    public function handle(RecurringBillTransaction $recurringBillTransaction, array $modelData, bool $isFulfilmentTransactionUpdated = false, bool $calculateParentTotals = true): RecurringBillTransaction
    {
        if (Arr::exists($modelData, 'net_amount')) {
            $netAmount = Arr::get($modelData, 'net_amount');
            $quantity  = $netAmount / $recurringBillTransaction->unit_cost;
            data_set($modelData, 'quantity', $quantity);
        }

        $recurringBillTransaction = $this->update($recurringBillTransaction, $modelData, ['data']);

        if ($recurringBillTransaction->fulfilmentTransaction && !$isFulfilmentTransactionUpdated) {
            UpdateFulfilmentTransaction::make()->action($recurringBillTransaction->fulfilmentTransaction, $modelData, true);
        }

        if (!Arr::exists($modelData, 'net_amount')) {
            $recurringBillTransaction = CalculateRecurringBillTransactionDiscountPercentage::make()->action($recurringBillTransaction, $this->hydratorsDelay);
            $recurringBillTransaction = CalculateRecurringBillTransactionTemporalQuantity::make()->action($recurringBillTransaction);
            $recurringBillTransaction = CalculateRecurringBillTransactionAmounts::make()->action($recurringBillTransaction);
            $recurringBillTransaction = CalculateRecurringBillTransactionCurrencyExchangeRates::make()->action($recurringBillTransaction);
        }

        if ($calculateParentTotals) {
            CalculateRecurringBillTotals::run($recurringBillTransaction->recurringBill);
        }


        return $recurringBillTransaction;
    }

    public function rules(): array
    {
        return [
            'quantity'   => ['sometimes', 'numeric', 'min:0'],
            'net_amount' => ['sometimes', 'numeric', 'min:0'],
            'end_date'   => ['sometimes', 'date'],
        ];
    }


    public function asController(RecurringBillTransaction $recurringBillTransaction, ActionRequest $actionRequest): void
    {
        $this->initialisationFromFulfilment($recurringBillTransaction->fulfilment, $actionRequest);

        $this->handle($recurringBillTransaction, $this->validatedData);
    }

    public function action(RecurringBillTransaction $recurringBillTransaction, array $modelData, bool $isFulfilmentTransactionUpdated = false, bool $calculateParentTotals = true): RecurringBillTransaction
    {
        $this->asAction = true;
        $this->initialisationFromFulfilment($recurringBillTransaction->fulfilment, $modelData);

        return $this->handle($recurringBillTransaction, $this->validatedData, $isFulfilmentTransactionUpdated, $calculateParentTotals);
    }

}
