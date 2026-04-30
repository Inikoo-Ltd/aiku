<?php

/*
 * author Arya Permana - Kirin
 * created on 02-07-2025-17h-39m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\PaymentGateway\Pastpay\WithPastpayConfiguration;
use App\Actions\Accounting\Traits\CalculatesPaymentWithBalance;
use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\MitSavedCard;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Checkout\CheckoutApiException;
use Checkout\CheckoutSdk;
use Checkout\Environment;
use Checkout\Payments\Request\PaymentRequest;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class PayOrderWithPastpay
{
    use AsAction;
    use CalculatesPaymentWithBalance;
    use WithPastpayConfiguration;

    /**
     * @throws \Checkout\CheckoutArgumentException
     */
    public function handle(Order $order): array
    {
        /** @var PaymentAccountShop $paymentAccountShop */
        $paymentAccountShop = $order->shop->paymentAccountShops()
            ->where('type', PaymentAccountTypeEnum::CHECKOUT)
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)->first();


        $secretKey = $paymentAccountShop->getCredentials()[1];


        $paymentAmounts = $this->calculatePaymentWithBalance(
            $order->total_amount,
            $order->customer->balance
        );


        $toPay = $paymentAmounts['total'];
        $toPay = (int)round((float)$toPay * 100);



        if ($toPay == 0) {
            return [
                'status' => 'ok',
            ];
        }

        try {
            $response = $this->pastpayInitiateOrder($order, [
                'totalPrice'       => [
                    'amount' => (float) $toPay,
                    'currency' => $order->currency->code
                ]
            ]);

            $amount = Arr::get($response, 'amount', 0) / 100;

            if (in_array(Arr::get($response, 'status'), ["Authorized", "Paid", "Captured"]) && Arr::get($response, 'approved')) {
                $status = PaymentStatusEnum::SUCCESS;
                $state  = PaymentStateEnum::COMPLETED;
            } else {
                $status = PaymentStatusEnum::FAIL;
                $state  = PaymentStateEnum::DECLINED;
            }

            $paymentData = [
                'reference'               => Arr::get($response, 'id'),
                'amount'                  => $amount,
                'status'                  => $status,
                'state'                   => $state,
                'type'                    => PaymentTypeEnum::PAYMENT,
                'is_mit'                  => false,
                'debug_mit_status'        => Arr::get($response, 'status'),
                'debug_mit_is_approved'   => (bool)Arr::get($response, 'approved', false),
                'payment_account_shop_id' => $paymentAccountShop->id,
                'source'                  => Arr::get($response, 'source'),
                'data'                    => [
                    'pastpay' => $response
                ]
            ];
            $payment     = StorePayment::make()->action($order->customer, $paymentAccountShop->paymentAccount, $paymentData);

            AttachPaymentToOrder::make()->action($order, $payment, [
                'amount' => $payment->amount
            ]);

            $result = [
                'status' => 'ok',
            ];
        } catch (CheckoutApiException $e) {
            // API error
            $error_details    = $e->error_details;
            $http_status_code = isset($e->http_metadata) ? $e->http_metadata->getStatusCode() : null;

            $result = [
                'debug'            => 'PayOrderWithPastpay.php',
                'status'           => 'error',
                'message'          => $e->getMessage(),
                'error_details'    => $error_details,
                'http_status_code' => $http_status_code,
            ];
        }

        return $result;
    }

    public string $commandSignature = 'test_pastpay';


    /**
     * @throws \Checkout\CheckoutArgumentException
     */
    public function asCommand(): int
    {
        $order = Order::find(1195254);

        $this->handle($order);


        return 1;
    }

}
