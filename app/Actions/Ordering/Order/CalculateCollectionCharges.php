<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Dec 2025 19:33:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Ordering\Transaction\DestroyTransaction;
use App\Actions\Ordering\Transaction\Traits\WithChargeTransactions;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Models\Billables\Charge;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class CalculateCollectionCharges
{
    use AsObject;
    use WithChargeTransactions;

    public function handle(Order $order): Order
    {


        /** @var Charge $charge */
        $charge = $order->shop->charges()->where('type', ChargeTypeEnum::COLLECTION)->where('state', ChargeStateEnum::ACTIVE)->first();


        $chargeApplies = $order->collection_address_id;

        if (!$charge) {
            $chargeApplies = false;
        }



        $chargeTransaction   = null;
        $chargeTransactionID = DB::table('transactions')->where('order_id', $order->id)
            ->leftJoin('charges', 'transactions.model_id', '=', 'charges.id')
            ->where('model_type', 'Charge')->where('charges.type', ChargeTypeEnum::COLLECTION->value)->value('transactions.id');

        if ($chargeTransactionID) {
            $chargeTransaction = Transaction::find($chargeTransactionID);
        }


        if ($chargeApplies) {
            $chargeAmount = Arr::get($charge->settings, 'amount');
            if ($chargeTransaction) {
                $this->updateChargeTransaction($chargeTransaction, $charge, $chargeAmount);
            } else {
                $this->storeChargeTransaction($order, $charge, $chargeAmount);
            }
        } elseif ($chargeTransaction) {
            DestroyTransaction::run($chargeTransaction);
        }

        return $order;
    }



}
