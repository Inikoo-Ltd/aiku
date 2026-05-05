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
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SuccessOrderWithPastpay
{
    use AsAction;
    use CalculatesPaymentWithBalance;
    use WithPastpayConfiguration;

    public function handle(Order $order, array $modelData): array
    {
        /** @var PaymentAccountShop $paymentAccountShop */
        $paymentAccountShop = $order->shop->paymentAccountShops()
            ->where('type', PaymentAccountTypeEnum::PASTPAY)
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->first();

        $this->paymentAccount = $paymentAccountShop->paymentAccount;

        // Recalculate the PastPay charge based on term + currency from settings
        $this->recalculatePastpayCharge($order);

        $paymentAmounts = $this->calculatePaymentWithBalance(
            $order->total_amount,
            $order->customer->balance
        );

        $toPay = $paymentAmounts['total'];
        $toPay = (int) round((float) $toPay * 100);

        if ($toPay == 0) {
            return ['status' => 'ok'];
        }

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

            AttachPaymentToOrder::make()->action($order, $payment, [
                'amount' => $payment->amount,
            ]);

            $result = ['status' => 'ok'];
        } catch (\Exception $e) {
            $result = [
                'debug'            => 'SuccessOrderWithPastpay.php',
                'status'           => 'error',
                'message'          => $e->getMessage(),
                'error_details'    => $e->getMessage(),
                'http_status_code' => $e->getCode() ?: null,
            ];
        }

        return $result;
    }

    /**
     * Mirrors the legacy charge recalculation:
     * – removes the existing PastPay charge on the order
     * – reads the charge rate for the term & currency from payment account settings
     * – adds the recalculated charge amount back onto the order
     */
    protected function recalculatePastpayCharge(Order $order): void
    {
        $pastpayData = is_array($order->pastpay_data)
            ? $order->pastpay_data
            : json_decode($order->pastpay_data, true);

        $term = Arr::get($pastpayData, 'term');

        if (!$term) {
            return;
        }

        $chargeName = $term . 'PastPay';

        $charge = $order->shop->charges()
            ->where('name', $chargeName)
            ->first();

        if (!$charge) {
            return;
        }

        $order->removeCharge($charge);

        $settings     = is_array($this->paymentAccount->settings)
            ? $this->paymentAccount->settings
            : json_decode($this->paymentAccount->settings, true);

        $currency     = $order->currency->code;
        $plans        = Arr::get($settings, $currency, []);
        $chargeRate   = Arr::get($plans, "{$term}.charge", 0);
        $chargeAmount = round($chargeRate * $order->to_pay_amount, 2);

        $order->addCharge($charge, $chargeAmount);
        $order->refresh();
    }

    public function asCommand(): int
    {
        $order = Order::where('slug', 'awp31048')->first();

        $this->handle($order, ['charges' => 30]);

        return 1;
    }
}
