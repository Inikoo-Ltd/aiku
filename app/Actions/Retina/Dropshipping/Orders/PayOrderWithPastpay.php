<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jun 2026 12:19:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\PaymentGateway\Pastpay\WithPastpayConfiguration;
use App\Actions\Accounting\Traits\CalculatesPaymentWithBalance;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\RetinaAction;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class PayOrderWithPastpay extends RetinaAction
{
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

        $termDays = Arr::get($modelData, 'days');
        $charges          = Arr::get($paymentAccountShop->data, 'charges.options', []);
        $chargePercentage = collect($charges)->where('days', $termDays)->first();
        $chargeAmount     = $paymentAmounts['total'] * ($chargePercentage['charge'] / 100);
        $toPay            = $paymentAmounts['total'] + $chargeAmount;

        $toPay = (int)round((float)$toPay * 100);

        if ($toPay == 0) {
            return [
                'status' => 'ok',
            ];
        }
        $amount = $toPay / 100;

        try {
            $response = $this->pastpayInitiateOrder($order, [
                'totalPrice' => [
                    'amount'   => (float)$amount,
                    'currency' => $order->currency->code
                ],
                'termDays'   => (int)$termDays,
            ]);


            UpdateOrder::run($order, [
                'data' => [
                    'pastpay' => [
                        'payment_account_shop_id' => $paymentAccountShop->id,
                        'charges'                 => $chargeAmount,
                        'termDays'                => $termDays,
                    ]
                ]
            ]);


            return [
                'status' => 'ok',
                'data'   => Arr::get($response, 'data.redirectUrl')
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

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');
        if ($order->customer_id == $this->customer->id) {
            return true;
        }

        return false;
    }

    public function asController(Order $order, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }

    public function rules(): array
    {
        return [
            'days' => [
                'required',
                'integer'
            ],
        ];
    }

    public string $commandSignature = 'test_pastpay';


    public function asCommand(): int
    {
        $order = Order::where('slug', 'awp31151')->first();

        $result = $this->handle($order, [
            'days' => 30
        ]);
        dd($result);

        return 1;
    }

}
