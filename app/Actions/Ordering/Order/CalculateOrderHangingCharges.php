<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 May 2025 19:23:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Ordering\Transaction\DestroyTransaction;
use App\Actions\Ordering\Transaction\Traits\WithChargeTransactions;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Ordering\Order\OrderChargesEngineEnum;
use App\Models\Billables\Charge;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class CalculateOrderHangingCharges
{
    use AsObject;
    use WithChargeTransactions;

    public function handle(Order $order): Order
    {
        if ($order->shipping_engine == OrderChargesEngineEnum::MANUAL) {
            return $order;
        }

        /** @var Charge $charge */
        $charge = $order->shop->charges()->where('type', ChargeTypeEnum::HANGING)->where('state', ChargeStateEnum::ACTIVE)->first();

        $chargeApplies = $this->checkIfChargeApplies($charge, $order);

        $chargeTransaction = null;
        $chargeTransactionID = DB::table('transactions')->where('order_id', $order->id)
            ->leftJoin('charges', 'transactions.model_id', '=', 'charges.id')
            ->where('model_type', 'Charge')->where('charges.type', ChargeTypeEnum::HANGING->value)->value('transactions.id');

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

    private function checkIfChargeApplies(?Charge $charge, Order $order): bool
    {
        if (! $charge) {
            return false;
        }
        if (Arr::get($charge->settings, 'rule_subject') == 'Order Items Net Amount') {
            return $this->match($order->goods_amount, Arr::get($charge->settings, 'rules'));
        }

        return false;
    }

    private function match($amount, string $rules): bool
    {
        [$operator, $limit] = explode(';', $rules);

        if ($operator == '>') {
            return $amount > $limit;
        } elseif ($operator == '<') {

            if ($amount == 0) {
                return false;
            }

            return $amount < $limit;
        }

        return false;
    }
}
