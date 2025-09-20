<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Aug 2025 21:52:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateHistoricProductInBasketTransactions
{
    use AsAction;

    public function handle(Product $product): void
    {
        $query = DB::table('transactions')
            ->select('transactions.id as transaction_id')
            ->where('asset_id', $product->asset_id)
            ->leftJoin('orders', 'transactions.order_id', '=', 'orders.id')
            ->where('orders.state', OrderStateEnum::CREATING)
            ->whereNull('transactions.deleted_at')
            ->whereNull('orders.deleted_at')
            ->orderBy('transactions.id')->get();


        foreach ($query as $row) {
            $transaction = Transaction::find($row->transaction_id);
            if ($transaction) {
                $this->updateTransactionHistoricProduct($transaction);
            }
        }
    }

    public function updateTransactionHistoricProduct(Transaction $transaction): void
    {
        if ($transaction->order->state !== OrderStateEnum::CREATING) {
            return;
        }

        /** @var Product $product */
        $product = $transaction->model;


        if ($transaction->historic_asset_id != $product->current_historic_asset_id) {
            $modelData = [];
            data_set($modelData, 'historic_asset_id', $product->current_historic_asset_id);


            $gross = $transaction->quantity_ordered * $product->price;
            if ($gross != $transaction->gross_amount) {
                $netAmount   = $gross;
                $shop        = $transaction->shop;
                $orgExchange = GetCurrencyExchange::run($shop->currency, $shop->organisation->currency);
                $grpExchange = GetCurrencyExchange::run($shop->currency, $shop->organisation->group->currency);


                data_set($modelData, 'gross_amount', $gross);
                data_set($modelData, 'net_amount', $netAmount);

                data_set($modelData, 'org_exchange', $orgExchange);
                data_set($modelData, 'org_net_amount', $orgExchange * $netAmount);

                data_set($modelData, 'grp_exchange', $grpExchange);
                data_set($modelData, 'grp_net_amount', $grpExchange * $netAmount);
            }

            $transaction->update($modelData);

            $changes = Arr::except($transaction->getChanges(), ['updated_at', 'last_fetched_at']);

            if (Arr::hasAny($changes, ['net_amount', 'gross_amount'])) {
                CalculateOrderTotalAmounts::run($transaction->order);
            }
        }
    }

}
