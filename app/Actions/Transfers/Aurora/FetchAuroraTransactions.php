<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 22 Sept 2022 02:54:36 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Transfers\Aurora\WithAuroraParsers;
use App\Transfers\SourceOrganisationService;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchAuroraTransactions
{
    use AsAction;
    use WithAuroraParsers;


    private SourceOrganisationService $organisationSource;

    public function handle(SourceOrganisationService $organisationSource, int $source_id, Order $order): ?Transaction
    {
        $this->organisationSource = $organisationSource;

        $transactionData = $organisationSource->fetchTransaction(id: $source_id);
        if (!$transactionData) {
            return null;
        }

        $transactionData['transaction']['org_exchange']   = GetHistoricCurrencyExchange::run($order->shop->currency, $order->organisation->currency, $transactionData['transaction']['date']);
        $transactionData['transaction']['grp_exchange']   = GetHistoricCurrencyExchange::run($order->shop->currency, $order->group->currency, $transactionData['transaction']['date']);
        $transactionData['transaction']['org_net_amount'] = $transactionData['transaction']['net_amount'] * $transactionData['transaction']['org_exchange'];
        $transactionData['transaction']['grp_net_amount'] = $transactionData['transaction']['net_amount'] * $transactionData['transaction']['grp_exchange'];


        if ($order->submitted_at) {
            $transactionData['transaction']['submitted_at'] = $order->submitted_at;
        }
        if ($order->in_warehouse_at) {
            $transactionData['transaction']['in_warehouse_at'] = $order->in_warehouse_at;
        }

        if ($transaction = Transaction::withTrashed()->where('source_id', $transactionData['transaction']['source_id'])->first()) {
            $transactionData['transaction']['deleted_at'] = null;
            $transaction = UpdateTransaction::make()->action(
                transaction: $transaction,
                modelData: $transactionData['transaction'],
                strict: false
            );
        } else {
            $transaction = StoreTransaction::make()->action(
                order: $order,
                historicAsset: $transactionData['historic_asset'],
                modelData: $transactionData['transaction'],
                hydratorsDelay: 10,
                strict: false
            );

            $sourceData = explode(':', $transaction->source_id);
            DB::connection('aurora')->table('Order Transaction Fact')
                ->where('Order Transaction Fact Key', $sourceData[1])
                ->update(['aiku_id' => $transaction->id]);
        }

        return $transaction;
    }



}
