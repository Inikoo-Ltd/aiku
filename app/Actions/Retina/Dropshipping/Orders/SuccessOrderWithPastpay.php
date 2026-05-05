<?php

/*
 * author Arya Permana - Kirin
 * created on 02-07-2025-17h-39m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\PaymentGateway\Pastpay\WithPastpayConfiguration;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\Traits\CalculatesPaymentWithBalance;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\CalculateOrderHangingCharges;
use App\Actions\Ordering\Transaction\Traits\WithChargeTransactions;
use App\Actions\RetinaAction;
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
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SuccessOrderWithPastpay extends RetinaAction
{
    use AsAction;
    use CalculatesPaymentWithBalance;
    use WithPastpayConfiguration;
    use WithChargeTransactions;

    public function handle(Order $order, array $modelData): false|string|\Illuminate\Http\RedirectResponse
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
            $amount = $toPay / 100;

            $paymentData = [
                'reference'               => $order->reference,
                'amount'                  => $amount,
                'status'                  => PaymentStatusEnum::SUCCESS,
                'state'                   => PaymentStateEnum::COMPLETED,
                'type'                    => PaymentTypeEnum::PAYMENT,
                'payment_account_shop_id' => $paymentAccountShop->id,
                'data'                    => [
                    'pastpay' => $modelData
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

            return redirect()->route('retina.orders.show', $order->slug);
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

    public function asController(Order $order, ActionRequest $request): false|string|\Illuminate\Http\RedirectResponse
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }

    public string $commandSignature = 'test_success_pastpay';

    public function asCommand(): int
    {
        $order = Order::where('slug', 'awp31048')->first();

        $this->handle($order, ['charges' => 30]);

        return 1;
    }
}
