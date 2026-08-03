<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Oct 2025 15:33:19 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\CheckoutCom;

use App\Actions\Accounting\OrderPaymentApiPoint\WebHooks\CheckoutComOrderPaymentFailure;
use App\Actions\Accounting\OrderPaymentApiPoint\WebHooks\CheckoutComOrderPaymentSuccess;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks\TopUpPaymentFailure;
use App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks\TopUpPaymentSuccess;
use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Enums\Accounting\OrderPaymentApiPoint\OrderPaymentApiPointStateEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Enums\Accounting\PaymentGatewayLog\PaymentGatewayLogStateEnum;
use App\Enums\Accounting\PaymentGatewayLog\PaymentGatewayLogStatusEnum;
use App\Enums\Accounting\TopUpPaymentApiPoint\TopUpPaymentApiPointStateEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\PaymentGatewayLog;
use App\Models\Accounting\TopUpPaymentApiPoint;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class ProcessCheckoutComPaymentGatewayLog
{
    use AsAction;
    use WithCheckoutCom {
        getCheckOutPayment as public;
    }

    public const array FAILURE_EVENT_TYPES = [
        'payment_declined',
        'payment_expired',
        'payment_canceled',
        'payment_voided',
        'payment_capture_declined',
        'payment_authentication_failed',
    ];

    /**
     * @throws \Throwable
     */
    public function handle(PaymentGatewayLog $paymentGatewayLog): PaymentGatewayLog
    {
        if ($paymentGatewayLog->state == PaymentGatewayLogStateEnum::PROCESSED) {
            return $paymentGatewayLog;
        }

        if ($paymentGatewayLog->operation == 'order' && $paymentGatewayLog->api_point_model_type == 'OrderPaymentApiPoint') {
            return $this->handleOrderOperation($paymentGatewayLog);
        }

        if ($paymentGatewayLog->operation == 'top_up' && $paymentGatewayLog->api_point_model_type == 'TopUpPaymentApiPoint') {
            return $this->handleTopUpOperation($paymentGatewayLog);
        }

        if ($paymentGatewayLog->operation == 'mit' && $paymentGatewayLog->order_id) {
            return $this->handleMitOperation($paymentGatewayLog);
        }

        if ($paymentGatewayLog->operation == 'mit_save_card') {
            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::NA);
        }

        return $paymentGatewayLog;
    }

    /**
     * @throws \Throwable
     */
    private function handleMitOperation(PaymentGatewayLog $paymentGatewayLog): PaymentGatewayLog
    {
        $order = Order::find($paymentGatewayLog->order_id);
        if (!$order) {
            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::FAIL);
        }

        if ($paymentGatewayLog->type == 'payment_captured') {
            return $this->processCapturedMit($paymentGatewayLog, $order);
        }

        if (in_array($paymentGatewayLog->type, self::FAILURE_EVENT_TYPES)) {
            return $this->processFailedMit($paymentGatewayLog, $order);
        }

        return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::NA);
    }

    /**
     * MIT orders submit independently of payment, so this never submits the order.
     * It only reconciles the payment record: link it when it exists, recreate it when
     * the synchronous charge response was lost.
     *
     * @throws \Throwable
     */
    private function processCapturedMit(PaymentGatewayLog $paymentGatewayLog, Order $order): PaymentGatewayLog
    {
        if (!$paymentGatewayLog->gateway_payment_id) {
            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::FAIL);
        }

        $existingPayment = Payment::where('reference', $paymentGatewayLog->gateway_payment_id)->first();
        if ($existingPayment) {
            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::OK, $existingPayment->id);
        }

        $paymentAccountShop = $order->shop->paymentAccountShops()
            ->where('type', PaymentAccountTypeEnum::CHECKOUT)
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->first();

        if (!$paymentAccountShop) {
            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::FAIL);
        }

        $checkoutComPayment = $this->getCheckOutPayment($paymentAccountShop, $paymentGatewayLog->gateway_payment_id);

        $status = Arr::get($checkoutComPayment, 'status');

        if (Arr::get($checkoutComPayment, 'error') || in_array($status, self::CHECKOUT_COM_PENDING_STATUSES)) {
            /** Left as preprocessed on purpose: a later duplicate delivery re-runs this action via
             * PreProcessCheckoutComPaymentGatewayLog, and the scheduled sweeper is the backstop */
            return $paymentGatewayLog;
        }

        if (in_array($status, self::CHECKOUT_COM_FAILURE_STATUSES)) {
            return $this->processFailedMit($paymentGatewayLog, $order);
        }

        if (!in_array($status, self::CHECKOUT_COM_CAPTURED_STATUSES)) {
            return $paymentGatewayLog;
        }

        $payment = DB::transaction(function () use ($order, $paymentAccountShop, $checkoutComPayment) {
            $paymentData = [
                'reference'               => Arr::get($checkoutComPayment, 'id'),
                'amount'                  => Arr::get($checkoutComPayment, 'amount', 0) / 100,
                'status'                  => PaymentStatusEnum::SUCCESS,
                'state'                   => PaymentStateEnum::COMPLETED,
                'type'                    => PaymentTypeEnum::PAYMENT,
                'is_mit'                  => true,
                'payment_account_shop_id' => $paymentAccountShop->id,
                'source'                  => Arr::get($checkoutComPayment, 'source'),
                'data'                    => [
                    'checkout_com' => $checkoutComPayment
                ]
            ];

            $payment = StorePayment::make()->action($order->customer, $paymentAccountShop->paymentAccount, $paymentData);

            AttachPaymentToOrder::make()->action($order, $payment, [
                'amount' => $payment->amount
            ]);

            return $payment;
        });

        Sentry::captureMessage(
            'Recovered lost MIT payment '.$payment->reference.' for order '.$order->id.' from checkout.com webhook'
        );

        $this->sendLatePaidOrderToWarehouse($order);

        return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::OK, $payment->id);
    }

    /**
     * Platform orders submit before their MIT charge settles, so a recovered late payment must
     * also nudge the order out of submitted. Outside the payment transaction on purpose: the
     * payment record must survive even when warehouse routing fails.
     */
    private function sendLatePaidOrderToWarehouse(Order $order): void
    {
        $order->refresh();

        if ($order->state != OrderStateEnum::SUBMITTED || $order->pay_status != OrderPayStatusEnum::PAID) {
            return;
        }

        try {
            SendOrderToWarehouse::run($order, [
                'warehouse_id' => $order->organisation->warehouses()->first()->id
            ]);
        } catch (\Throwable $e) {
            Sentry::captureException($e);
        }
    }

    private function processFailedMit(PaymentGatewayLog $paymentGatewayLog, Order $order): PaymentGatewayLog
    {
        $paidPayment = Payment::where('reference', $paymentGatewayLog->gateway_payment_id)
            ->where('status', PaymentStatusEnum::SUCCESS)
            ->first();

        if ($paidPayment) {
            Sentry::captureMessage(
                'MIT payment '.$paidPayment->reference.' failed ('.$paymentGatewayLog->type.') after being recorded as paid for order '.$order->id.' — needs manual review'
            );

            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::FAIL, $paidPayment->id);
        }

        return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::NA);
    }

    /**
     * @throws \Throwable
     */
    private function handleOrderOperation(PaymentGatewayLog $paymentGatewayLog): PaymentGatewayLog
    {
        $orderPaymentApiPoint = OrderPaymentApiPoint::find($paymentGatewayLog->api_point_model_id);
        if (!$orderPaymentApiPoint) {
            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::FAIL);
        }

        if ($paymentGatewayLog->type == 'payment_captured') {
            return $this->processCapturedPayment($paymentGatewayLog, $orderPaymentApiPoint);
        }

        if (in_array($paymentGatewayLog->type, self::FAILURE_EVENT_TYPES)) {
            return $this->processFailedPayment($paymentGatewayLog, $orderPaymentApiPoint);
        }

        return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::NA);
    }

    /**
     * @throws \Throwable
     */
    private function handleTopUpOperation(PaymentGatewayLog $paymentGatewayLog): PaymentGatewayLog
    {
        $topUpPaymentApiPoint = TopUpPaymentApiPoint::find($paymentGatewayLog->api_point_model_id);
        if (!$topUpPaymentApiPoint) {
            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::FAIL);
        }

        if ($paymentGatewayLog->type == 'payment_captured') {
            return $this->processCapturedTopUp($paymentGatewayLog, $topUpPaymentApiPoint);
        }

        if (in_array($paymentGatewayLog->type, self::FAILURE_EVENT_TYPES)) {
            return $this->processFailedTopUp($paymentGatewayLog, $topUpPaymentApiPoint);
        }

        return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::NA);
    }

    /**
     * @throws \Throwable
     */
    private function processCapturedTopUp(PaymentGatewayLog $paymentGatewayLog, TopUpPaymentApiPoint $topUpPaymentApiPoint): PaymentGatewayLog
    {
        $paymentAccountShop = PaymentAccountShop::find(Arr::get($topUpPaymentApiPoint->data, 'payment_account_shop_id.checkout'));
        if (!$paymentAccountShop || !$paymentGatewayLog->gateway_payment_id) {
            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::FAIL);
        }

        $existingPayment = Payment::where('payment_account_shop_id', $paymentAccountShop->id)
            ->where('reference', $paymentGatewayLog->gateway_payment_id)
            ->first();

        if ($existingPayment) {
            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::OK, $existingPayment->id);
        }

        $checkoutComPayment = $this->getCheckOutPayment($paymentAccountShop, $paymentGatewayLog->gateway_payment_id);

        $status = Arr::get($checkoutComPayment, 'status');

        if (Arr::get($checkoutComPayment, 'error') || in_array($status, self::CHECKOUT_COM_PENDING_STATUSES)) {
            /** Left as preprocessed on purpose: a later duplicate delivery re-runs this action via
             * PreProcessCheckoutComPaymentGatewayLog, and the scheduled sweeper is the backstop */
            return $paymentGatewayLog;
        }

        if (in_array($status, self::CHECKOUT_COM_FAILURE_STATUSES)) {
            return $this->processFailedTopUp($paymentGatewayLog, $topUpPaymentApiPoint);
        }

        if (!in_array($status, self::CHECKOUT_COM_CAPTURED_STATUSES)) {
            return $paymentGatewayLog;
        }

        TopUpPaymentSuccess::make()->processSuccess($checkoutComPayment, $topUpPaymentApiPoint, $paymentAccountShop);

        $topUpPaymentApiPoint->refresh();

        return $this->markProcessed(
            $paymentGatewayLog,
            PaymentGatewayLogStatusEnum::OK,
            Arr::get($topUpPaymentApiPoint->data, 'payment_id')
        );
    }

    private function processFailedTopUp(PaymentGatewayLog $paymentGatewayLog, TopUpPaymentApiPoint $topUpPaymentApiPoint): PaymentGatewayLog
    {
        if ($topUpPaymentApiPoint->state == TopUpPaymentApiPointStateEnum::IN_PROCESS) {
            TopUpPaymentFailure::make()->processFailure(
                $topUpPaymentApiPoint,
                Arr::get($paymentGatewayLog->payload, 'data', [])
            );

            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::OK);
        }

        $paidPayment = Payment::where('reference', $paymentGatewayLog->gateway_payment_id)
            ->where('status', PaymentStatusEnum::SUCCESS)
            ->first();

        if ($paidPayment) {
            Sentry::captureMessage(
                'Checkout.com payment '.$paidPayment->reference.' failed ('.$paymentGatewayLog->type.') after being recorded as paid for top up api point '.$topUpPaymentApiPoint->id.' — needs manual review'
            );

            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::FAIL, $paidPayment->id);
        }

        return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::NA);
    }

    /**
     * @throws \Throwable
     */
    private function processCapturedPayment(PaymentGatewayLog $paymentGatewayLog, OrderPaymentApiPoint $orderPaymentApiPoint): PaymentGatewayLog
    {
        $paymentAccountShop = PaymentAccountShop::find(Arr::get($orderPaymentApiPoint->data, 'payment_methods.checkout'));
        if (!$paymentAccountShop || !$paymentGatewayLog->gateway_payment_id) {
            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::FAIL);
        }

        $existingPayment = Payment::where('payment_account_shop_id', $paymentAccountShop->id)
            ->where('reference', $paymentGatewayLog->gateway_payment_id)
            ->first();

        if ($existingPayment) {
            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::OK, $existingPayment->id);
        }

        $checkoutComPayment = $this->getCheckOutPayment($paymentAccountShop, $paymentGatewayLog->gateway_payment_id);

        $status = Arr::get($checkoutComPayment, 'status');

        if (Arr::get($checkoutComPayment, 'error') || in_array($status, self::CHECKOUT_COM_PENDING_STATUSES)) {
            /** Left as preprocessed on purpose: a later duplicate delivery re-runs this action via
             * PreProcessCheckoutComPaymentGatewayLog, and the scheduled sweeper is the backstop */
            return $paymentGatewayLog;
        }

        if (in_array($status, self::CHECKOUT_COM_FAILURE_STATUSES)) {
            return $this->processFailedPayment($paymentGatewayLog, $orderPaymentApiPoint);
        }

        if (!in_array($status, self::CHECKOUT_COM_CAPTURED_STATUSES)) {
            return $paymentGatewayLog;
        }

        CheckoutComOrderPaymentSuccess::make()->processSuccessfulPayment($orderPaymentApiPoint, $paymentAccountShop, $checkoutComPayment);

        $orderPaymentApiPoint->refresh();

        return $this->markProcessed(
            $paymentGatewayLog,
            PaymentGatewayLogStatusEnum::OK,
            Arr::get($orderPaymentApiPoint->data, 'payment_id')
        );
    }

    private function processFailedPayment(PaymentGatewayLog $paymentGatewayLog, OrderPaymentApiPoint $orderPaymentApiPoint): PaymentGatewayLog
    {
        if ($orderPaymentApiPoint->state == OrderPaymentApiPointStateEnum::IN_PROCESS) {
            CheckoutComOrderPaymentFailure::make()->processFailure(
                $orderPaymentApiPoint,
                Arr::get($paymentGatewayLog->payload, 'data', [])
            );

            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::OK);
        }

        $paidPayment = Payment::where('reference', $paymentGatewayLog->gateway_payment_id)
            ->where('status', PaymentStatusEnum::SUCCESS)
            ->first();

        if ($paidPayment) {
            Sentry::captureMessage(
                'Checkout.com payment '.$paidPayment->reference.' failed ('.$paymentGatewayLog->type.') after being recorded as paid for order '.$orderPaymentApiPoint->order_id.' — needs manual review'
            );

            return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::FAIL, $paidPayment->id);
        }

        return $this->markProcessed($paymentGatewayLog, PaymentGatewayLogStatusEnum::NA);
    }

    private function markProcessed(PaymentGatewayLog $paymentGatewayLog, PaymentGatewayLogStatusEnum $status, ?int $paymentId = null): PaymentGatewayLog
    {
        $dataToUpdate = [
            'state'        => PaymentGatewayLogStateEnum::PROCESSED,
            'status'       => $status,
            'processed_at' => now(),
        ];

        if ($paymentId) {
            $dataToUpdate['payment_id'] = $paymentId;
        }

        $paymentGatewayLog->update($dataToUpdate);

        return $paymentGatewayLog;
    }

}
