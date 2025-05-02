<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 May 2025 19:23:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Ordering\Transaction\DestroyTransaction;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Ordering\Order\OrderChargesEngineEnum;
use App\Models\Billables\Charge;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class CalculateOrderHangingCharges
{
    use AsObject;

    public function handle(Order $order): Order
    {
        if ($order->shipping_engine == OrderChargesEngineEnum::MANUAL) {
            return $order;
        }

        $charge = $order->shop->charges()->where('type', ChargeTypeEnum::HANGING)->where('state', ChargeStateEnum::ACTIVE)->first();


        $chargeApplies = $this->checkIfChargeApplies($charge, $order);


        $chargeTransaction = $order->transactions()->where('model_type', 'Charge')
            ->where('model_id', $charge->id)->first();
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
        if (!$charge) {
            return false;
        }
        if (Arr::get($charge->settings, 'rule_subject') == 'Order Items Net Amount') {
            return $this->match($order->goods_amount, Arr::get($charge->settings, 'rules'));
        }

        return false;
    }

    private function storeChargeTransaction(Order $order, Charge $charge, $chargeAmount): Transaction
    {
        return StoreTransaction::run(
            $order,
            $charge->historicAsset,
            [
                'quantity_ordered' => 1,
                'gross_amount'     => $chargeAmount,
                'net_amount'       => $chargeAmount,

            ],
            false
        );
    }


    private function updateChargeTransaction(Transaction $transaction, Charge $charge, $chargeAmount): Transaction
    {
        return UpdateTransaction::run(
            $transaction,
            [
                'model_id'          => $charge->id,
                'asset_id'          => $charge->asset_id,
                'historic_asset_id' => $charge->historicAsset->id,
                'gross_amount'      => $chargeAmount ?? 0,
                'net_amount'        => $chargeAmount ?? 0,
            ],
            false
        );
    }


    private function match($amount, string $rules): bool
    {
        list($operator, $limit) = explode(';', $rules);

        if ($operator == '>') {
            return $amount > $limit;
        } elseif ($operator == '<') {
            return $amount < $limit;
        }

        return false;
    }


}
