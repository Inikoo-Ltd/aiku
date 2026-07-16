<?php

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\PaymentGateway\Btree\ChargeBtreePaymentMethod;
use App\Actions\Accounting\PaymentGateway\Btree\MakePaymentUsingBtree;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\Traits\CalculatesPaymentWithBalance;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\RetinaAction;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class PayOrderWithBtree extends RetinaAction
{
    use CalculatesPaymentWithBalance;

    public function handle(Order $order, array $modelData): array
    {
        /** @var PaymentAccountShop $paymentAccountShop */
        $paymentAccountShop = $order->shop->paymentAccountShops()
            ->whereIn('type', [PaymentAccountTypeEnum::BRAINTREE, PaymentAccountTypeEnum::BRAINTREE_PAYPAL])
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->first();

        if (!$paymentAccountShop) {
            return [
                'status'  => 'error',
                'message' => __('Braintree is not enabled in this shop'),
            ];
        }

        $paymentAmounts = $this->calculatePaymentWithBalance(
            $order->total_amount,
            $order->customer->balance
        );

        $toPay = round($paymentAmounts['by_other'], 2);

        if ($toPay == 0) {
            return [
                'status' => 'ok',
            ];
        }

        try {
            $transaction = ChargeBtreePaymentMethod::run(
                $paymentAccountShop->paymentAccount,
                Arr::get($modelData, 'payment_method_nonce'),
                (string)$toPay,
                [
                    'orderId' => (string)$order->reference
                ]
            );

            if (!in_array(Arr::get($transaction, 'status'), MakePaymentUsingBtree::SUCCESS_TRANSACTION_STATUSES)) {
                return [
                    'status'  => 'error',
                    'message' => __('Braintree transaction was not approved'),
                ];
            }

            $paymentData = [
                'reference'               => Arr::get($transaction, 'legacyId', Arr::get($transaction, 'id')),
                'amount'                  => $toPay,
                'status'                  => PaymentStatusEnum::SUCCESS,
                'state'                   => PaymentStateEnum::COMPLETED,
                'type'                    => PaymentTypeEnum::PAYMENT,
                'payment_account_shop_id' => $paymentAccountShop->id,
                'data'                    => [
                    'btree' => $transaction
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

            if ($order->total_amount > $order->payment_amount && $order->customer->balance > 0) {
                SettleRetinaOrderWithBalance::run($order);
            }

            $order->refresh();

            SubmitOrder::run($order);

            return [
                'status' => 'ok',
                'data'   => [
                    'payment_id'     => $payment->id,
                    'transaction_id' => Arr::get($transaction, 'id'),
                ]
            ];
        } catch (\Exception $e) {
            return [
                'debug'   => 'PayOrderWithBtree.php',
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');

        return $order->customer_id == $this->customer->id;
    }

    public function rules(): array
    {
        return [
            'payment_method_nonce' => [
                'required',
                'string'
            ],
        ];
    }

    public function asController(Order $order, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
