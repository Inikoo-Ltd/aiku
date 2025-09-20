<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Sept 2024 23:16:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Ordering\Transaction\StoreTransactionFromAdjustment;
use App\Actions\Ordering\Transaction\StoreTransactionFromCharge;
use App\Actions\Ordering\Transaction\StoreTransactionFromShipping;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Models\Billables\Charge;
use App\Models\Ordering\Order;
use App\Models\Ordering\ShippingZone;
use App\Models\Ordering\Transaction;
use App\Transfers\SourceOrganisationService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchAuroraNoProductTransactions
{
    use AsAction;

    private SourceOrganisationService $organisationSource;

    public function handle(SourceOrganisationService $organisationSource, int $source_id, Order $order): ?Transaction
    {
        $this->organisationSource = $organisationSource;
        $transactionData          = $organisationSource->fetchNoProductTransaction(id: $source_id, order: $order);
        if (!$transactionData) {
            return null;
        }

        if ($order->submitted_at) {
            $transactionData['transaction']['submitted_at'] = $order->submitted_at;
        }

        $transactionData['transaction']['state']  = $order->state->value;
        $transactionData['transaction']['status'] = $order->status->value;

        if ($transactionData['type'] == 'Adjustment') {
            $transaction = $this->processAdjustmentTransaction($order, $transactionData);
        } elseif ($transactionData['type'] == 'Shipping') {
            $transaction = $this->processShippingTransaction($order, $transactionData);
        } elseif ($transactionData['type'] == 'Charges') {
            $transaction = $this->processChargeTransaction($order, $transactionData);
        } else {
            dd($transactionData['type']);
        }


        return $transaction;
    }

    public function processAdjustmentTransaction(Order $order, array $transactionData): Transaction
    {
        if ($transaction = Transaction::withTrashed()->where('source_alt_id', $transactionData['transaction']['source_alt_id'])->first()) {
            $transactionData['transaction']['deleted_at'] = null;
            $transaction = UpdateTransaction::make()->action(
                transaction: $transaction,
                modelData: $transactionData['transaction'],
                strict: false
            );
        } else {
            $transaction = StoreTransactionFromAdjustment::make()->action(
                order: $order,
                adjustment: $transactionData['adjustment'],
                modelData: $transactionData['transaction'],
                strict: false
            );

            $sourceData = explode(':', $transaction->source_alt_id);
            DB::connection('aurora')->table('Order No Product Transaction Fact')
                ->where('Order No Product Transaction Fact Key', $sourceData[1])
                ->update(['aiku_id' => $transaction->id]);
        }

        return $transaction;
    }

    public function processShippingTransaction(Order $order, array $transactionData): Transaction
    {
        /** @var ShippingZone $shippingZone */
        $shippingZone = Arr::get($transactionData, 'model');

        if ($transaction = Transaction::withTrashed()->where('source_alt_id', $transactionData['transaction']['source_alt_id'])->first()) {

            data_set($transactionData, 'transaction.model', 'ShippingZone');
            if ($shippingZone) {
                data_set($transactionData, 'transaction.model_id', $shippingZone->id);
                data_set($transactionData, 'transaction.asset_id', $shippingZone->id);
                data_set($transactionData, 'transaction.historic_asset_id', $shippingZone->current_historic_asset_id);
            }

            $transactionData['transaction']['deleted_at'] = null;
            $transaction = UpdateTransaction::make()->action(
                transaction: $transaction,
                modelData: $transactionData['transaction'],
                strict: false
            );
        } else {
            $transaction = StoreTransactionFromShipping::make()->action(
                order: $order,
                shippingZone: $shippingZone,
                modelData: $transactionData['transaction'],
                strict: false
            );

            $sourceData = explode(':', $transaction->source_alt_id);
            DB::connection('aurora')->table('Order No Product Transaction Fact')
                ->where('Order No Product Transaction Fact Key', $sourceData[1])
                ->update(['aiku_id' => $transaction->id]);
        }


        return $transaction;
    }

    public function processChargeTransaction(Order $order, array $transactionData): Transaction
    {
        /** @var Charge $charge */
        $charge = Arr::get($transactionData, 'model');

        if ($transaction = Transaction::withTrashed()->where('source_alt_id', $transactionData['transaction']['source_alt_id'])->first()) {

            data_set($transactionData, 'transaction.model', 'Charge');
            if ($charge) {
                data_set($transactionData, 'transaction.model_id', $charge->id);
                data_set($transactionData, 'transaction.asset_id', $charge->id);
                data_set($transactionData, 'transaction.historic_asset_id', $charge->current_historic_asset_id);
            }
            $transactionData['transaction']['deleted_at'] = null;
            $transaction = UpdateTransaction::make()->action(
                transaction: $transaction,
                modelData: $transactionData['transaction'],
                strict: false
            );
        } else {
            $transaction = StoreTransactionFromCharge::make()->action(
                order: $order,
                charge: $transactionData['model'],
                modelData: $transactionData['transaction'],
                strict: false
            );

            $sourceData = explode(':', $transaction->source_alt_id);
            DB::connection('aurora')->table('Order No Product Transaction Fact')
                ->where('Order No Product Transaction Fact Key', $sourceData[1])
                ->update(['aiku_id' => $transaction->id]);
        }

        return $transaction;
    }

}
