<?php

/*
 * author Arya Permana - Kirin
 * created on 02-07-2025-17h-39m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\Traits\CalculatesPaymentWithBalance;
use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\MitSavedCard;
use App\Models\Ordering\Order;
use Checkout\CheckoutApiException;
use Checkout\CheckoutSdk;
use Checkout\Environment;
use Checkout\Payments\Request\PaymentRequest;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class PayRemainingOrderWithMitCard
{
    use AsAction;
    use WithCheckoutCom;
    use CalculatesPaymentWithBalance;

    /**
     * @throws \Checkout\CheckoutArgumentException
     * @throws \Laravel\Octane\Exceptions\DdException
     */
    public function handle(Order $order, MitSavedCard $mitSavedCard): array
    {
        $paymentAccountShop = $order->shop->paymentAccountShops()
            ->where('type', PaymentAccountTypeEnum::CHECKOUT)
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)->first();



        $secretKey = $paymentAccountShop->getCredentials()[1];


        $toPay = $order->total_amount - $order->payment_amount;
        $toPay = intval($toPay * 100);

        if ($toPay == 0) {
            return [
                'status' => 'ok',
            ];
        }

        $api = CheckoutSdk::builder()->staticKeys()
            ->environment(app()->environment('production') ? Environment::production() : Environment::sandbox())
            ->secretKey($secretKey)
            ->build();

        $channelID = $paymentAccountShop->getCheckoutComChannel();


        $request                        = new PaymentRequest();
        $request->source                = [
            'type' => 'id',
            'id'   => Arr::get($mitSavedCard->data, 'payment.source.id'),
        ];
        $request->processing_channel_id = $channelID;

        $request->amount                = $toPay;
        $request->currency              = $order->currency->code;
        $request->payment_type          = 'Unscheduled';
        $request->merchant_initiated    = true;
        $request->previous_payment_id   = $mitSavedCard->token;
        $request->processing            = [
            'merchant_initiated_reason' => 'delayed_charge'
        ];

        try {
            $response = $api->getPaymentsClient()->requestPayment($request);

            $amount = Arr::get($response, 'amount', 0) / 100;

            $paymentData = [
                'reference'               => Arr::get($response, 'id'),
                'amount'                  => $amount,
                'status'                  => PaymentStatusEnum::SUCCESS,
                'state'                   => PaymentStateEnum::COMPLETED,
                'type'                    => PaymentTypeEnum::PAYMENT,
                'payment_account_shop_id' => $paymentAccountShop->id,
                'data' => [
                    'checkout_com' => $response
                ]
            ];
            $payment = StorePayment::make()->action($order->customer, $paymentAccountShop->paymentAccount, $paymentData);

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
                'status' => 'error',
                'message' => $e->getMessage(),
                'error_details' => $error_details,
                'http_status_code' => $http_status_code,
            ];
            print_r($result);

        }

        return $result;
    }

    public string $commandSignature = 'test_pay';

    /**
     * @throws \Checkout\CheckoutArgumentException
     * @throws \Laravel\Octane\Exceptions\DdException
     */
    public function asCommand(): int
    {
        $order = Order::find(1186846);

        $mitSavedCard = MitSavedCard::where('customer_id', $order->customer_id)->first();


        $this->handle($order, $mitSavedCard);


        return 1;
    }

}
