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
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\RetinaAction;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class PayOrderWithPastpay extends RetinaAction
{
    use AsAction;
    use WithAttributes;
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

        $charges = Arr::get($this->paymentAccount->data, 'charges.options', []);
        $chargePercentage = collect($charges)->where('days', Arr::get($modelData, 'days', 30))->first();
        $chargeAmount = $paymentAmounts['total'] * ($chargePercentage['charge'] / 100);
        $toPay = $paymentAmounts['total'] + $chargeAmount;

        $toPay = (int)round((float)$toPay * 100);

        if ($toPay == 0) {
            return [
                'status' => 'ok',
            ];
        }

        try {
            $amount = $toPay / 100;

            $response = $this->pastpayInitiateOrder($order, [
                'totalPrice'       => [
                    'amount' => (float) $amount,
                    'currency' => $order->currency->code
                ],
                'termDays' => Arr::get($modelData, 'days', 30),
            ]);

            UpdateOrder::run($order, [
                'data' => [
                    'pastpay' => [
                        'charges' => $chargeAmount,
                        'termDays' => Arr::get($modelData, 'days', 30),
                    ]
                ]
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

    public function rules(): array
    {
        return [
            'days' => [
                'required',
                'integer',
                Rule::in([30, 60]),
            ],
        ];
    }

    public string $commandSignature = 'test_pastpay';


    public function asCommand(): int
    {
        $order = Order::where('slug', 'awp31151')->first();

        $this->handle($order, [
            'charges' => 30
        ]);


        return 1;
    }

}
