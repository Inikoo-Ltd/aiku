<?php

namespace App\Actions\Retina;

use App\Actions\Accounting\PaymentGateway\Btree\GenerateBtreeClientToken;
use App\Actions\Accounting\Traits\CalculatesPaymentWithBalance;
use App\Actions\RetinaAction;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetBtreeClientTokenToPayOrder extends RetinaAction
{
    use AsAction;
    use CalculatesPaymentWithBalance;

    public function handle(Order $order): array
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

        return [
            'status'        => 'success',
            'token'         => GenerateBtreeClientToken::run($paymentAccountShop->paymentAccount),
            'amount_to_pay' => $paymentAmounts['by_other'],
            'currency_code' => $order->currency->code,
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');

        return $order->customer_id == $this->customer->id;
    }

    public function asController(Order $order, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($order);
    }
}
