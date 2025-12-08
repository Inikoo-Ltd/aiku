<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Sept 2025 22:51:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Accounting\Payment;

use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\Traits\WithChargeTransactions;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
use App\Models\Billables\Charge;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait WithPlaceOrderByPaymentMethod
{
    use WithChargeTransactions;

    /**
     * Common implementation to place an order for a given customer's current basket
     * and set the payment method designation, wrapped in a DB transaction.
     *
     * @throws \Throwable
     */
    protected function placeOrderByPaymentMethod(Customer $customer, OrderToBePaidByEnum $method): array
    {
        $order = Order::find($customer->current_order_in_basket_id);
        if (! $order) {
            return [
                'success' => false,
                'reason' => 'Order not found',
                'order' => null,
            ];
        }

        $order = DB::transaction(function () use ($order, $method) {
            if ($method == OrderToBePaidByEnum::CASH_ON_DELIVERY) {
                $order = $this->addCashOnDeliveryCharges($order);
            }

            $order->updateQuietly([
                'to_be_paid_by' => $method,
            ]);

            return SubmitOrder::run($order);
        });

        return [
            'success' => true,
            'reason' => 'Order submitted successfully',
            'order' => $order,
        ];
    }

    public function addCashOnDeliveryCharges(Order $order): Order
    {
        /** @var Charge $charge */
        $charge = $order->shop->charges()->where('type', ChargeTypeEnum::COD)->where('state', ChargeStateEnum::ACTIVE)->first();

        $cashOnDeliveryTransaction = null;
        $cashOnDeliveryTransactionID = DB::table('transactions')->where('order_id', $order->id)
            ->leftJoin('charges', 'transactions.model_id', '=', 'charges.id')
            ->where('model_type', 'Charge')->where('charges.type', ChargeTypeEnum::COD->value)->value('transactions.id');

        if ($cashOnDeliveryTransactionID) {
            $cashOnDeliveryTransaction = Transaction::find($cashOnDeliveryTransactionID);
        }

        $chargeAmount = Arr::get($charge->settings, 'amount');
        if ($cashOnDeliveryTransaction) {
            $this->updateChargeTransaction($cashOnDeliveryTransaction, $charge, $chargeAmount);
        } else {
            $this->storeChargeTransaction($order, $charge, $chargeAmount);
        }

        $order->refresh();

        return $order;
    }
}
