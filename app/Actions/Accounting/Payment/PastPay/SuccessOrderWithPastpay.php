<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 17 Jul 2026 15:18:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\PastPay;

use App\Actions\Accounting\OrderPaymentApiPoint\UpdateOrderPaymentApiPoint;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\Traits\CalculatesPaymentWithBalance;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\Traits\WithChargeTransactions;
use App\Actions\Retina\Dropshipping\Orders\SettleRetinaOrderWithBalance;
use App\Actions\RetinaAction;
use App\Enums\Accounting\OrderPaymentApiPoint\OrderPaymentApiPointStateEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Billables\Charge;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SuccessOrderWithPastpay extends RetinaAction
{
    use AsAction;
    use CalculatesPaymentWithBalance;
    use WithPastpayConfiguration;
    use WithChargeTransactions;

    public function handle(Order $order): false|string|RedirectResponse
    {
        /** @var PaymentAccountShop $paymentAccountShop */
        $paymentAccountShop = $order->shop->paymentAccountShops()
            ->where('type', PaymentAccountTypeEnum::PASTPAY)
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->first();

        $this->paymentAccount = $paymentAccountShop->paymentAccount;

        $paymentAmounts = $this->calculatePaymentWithBalance(
            $order->total_amount,
            $order->customer->balance
        );

        $chargeAmount = Arr::get($order->data, 'pastpay.charges');
        $toPay = $paymentAmounts['total'] + $chargeAmount;
        $toPay = (int) round((float) $toPay * 100);

        try {
            $orderPaymentApiPoint = $order->orderPaymentApiPoint;
            $amount = $toPay / 100;

            $paymentData = [
                'reference'               => $order->reference,
                'amount'                  => $amount,
                'status'                  => PaymentStatusEnum::SUCCESS,
                'state'                   => PaymentStateEnum::COMPLETED,
                'type'                    => PaymentTypeEnum::PAYMENT,
                'payment_account_shop_id' => $paymentAccountShop->id,
                'api_point_type'          => class_basename($orderPaymentApiPoint),
                'api_point_id'            => $orderPaymentApiPoint->id,
                'data'                    => [
                    'pastpay' => $order->data
                ],
            ];

            $payment = StorePayment::make()->action(
                $order->customer,
                $paymentAccountShop->paymentAccount,
                $paymentData
            );

            /** @var Charge $charge */
            $charge = $order->shop->charges()->where('type', ChargeTypeEnum::PAYMENT)
                ->where('state', ChargeStateEnum::ACTIVE)->first();

            $this->storeChargeTransaction($order, $charge, $chargeAmount);

            AttachPaymentToOrder::make()->action($order, $payment, [
                'amount' => $payment->amount,
            ]);

            if ($order->total_amount > $order->payment_amount && $order->customer->balance > 0) {
                SettleRetinaOrderWithBalance::run($order);
            }

            UpdateOrderPaymentApiPoint::run(
                $orderPaymentApiPoint,
                [
                    'state'        => OrderPaymentApiPointStateEnum::SUCCESS,
                    'processed_at' => now(),
                    'data'         => [
                        'payment_id' => $payment->id,
                    ]
                ]
            );


            $order->refresh();

            SubmitOrder::run($order);

            return redirect()->route('retina.ecom.orders.show', $order->slug);
        } catch (\Exception $e) {
            $result = [
                'debug'            => 'SuccessOrderWithPastpay.php',
                'status'           => 'error',
                'message'          => $e->getMessage(),
                'error_details'    => $e->getMessage(),
                'http_status_code' => $e->getCode() ?: null,
            ];
        }

        return json_encode($result);
    }

    public function asController(Order $order, ActionRequest $request): false|string|RedirectResponse
    {
        $this->initialisation($request);

        return $this->handle($order);
    }

    public string $commandSignature = 'test_success_pastpay';

    public function asCommand(): int
    {
        $order = Order::where('slug', 'awp31151')->first();

        $this->handle($order, []);

        return 1;
    }
}
