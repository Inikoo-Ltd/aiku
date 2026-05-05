<?php

/*
 * author Arya Permana - Kirin
 * created on 02-07-2025-17h-39m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\PaymentGateway\Pastpay\WithPastpayConfiguration;
use App\Actions\Accounting\Traits\CalculatesPaymentWithBalance;
use App\Actions\RetinaAction;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class PayOrderWithPastpay extends RetinaAction
{
    use AsAction;
    use CalculatesPaymentWithBalance;
    use WithPastpayConfiguration;

    public function handle(Order $order, array $modelData): array
    {
        /** @var PaymentAccountShop $paymentAccountShop */
        $paymentAccountShop = $order->shop->paymentAccountShops()
            ->where('type', PaymentAccountTypeEnum::PASTPAY)
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)->first();

        $this->paymentAccount = $paymentAccountShop->paymentAccount;

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
                ],
                'termDays' => Arr::get($modelData, 'charges', 30),
            ]);

            return [
                'status' => 'ok',
                'data' => Arr::get($response, 'data.redirectUrl')
            ];
        } catch (\Exception $e) {
            // API error
            $error_details    = $e->getMessage();
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

    public function asController(Order $order, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }

    public string $commandSignature = 'test_pastpay';


    public function asCommand(): int
    {
        $order = Order::where('slug', 'awp31048')->first();

        $this->handle($order, [
            'charges' => 30
        ]);


        return 1;
    }

}
