<?php

namespace App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks;

use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\RetinaWebhookAction;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\TopUpPaymentApiPoint;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class TopUpPaymentCompleted extends RetinaWebhookAction
{
    use WithCheckoutCom;

    public function handle(TopUpPaymentApiPoint $topUpPaymentApiPoint, array $modelData): array
    {
        $paymentAccountShopId = Arr::get($topUpPaymentApiPoint->data, 'payment_account_shop_id');
        $paymentAccountShop   = PaymentAccountShop::find($paymentAccountShopId)->first();

        $checkoutComPayment = $this->getCheckOutPayment(
            $paymentAccountShop,
            $modelData['cko-payment-id']
        );

        if (Arr::get($checkoutComPayment, 'error')) {
            return [
                'status'         => 'pending',
                'payment_status' => 'Connection Error',
                'msg'            => __('Error connecting to payment gateway, we will try again now')

            ];
        }

        $status = Arr::get($checkoutComPayment, 'status', 'Error');
        if (in_array($status, ['Pending', 'Retry Scheduled'])) {
            return [
                'status'         => 'pending',
                'payment_status' => $status,
                'msg'            => __('Payment is still pending, we will try again now')
            ];
        }

        if (in_array($status, ['Voided', 'Declined', 'Cancelled', 'Expired'])) {
            TopUpPaymentFailure::make()->processFailure($topUpPaymentApiPoint, $checkoutComPayment);

            return [
                'status'         => 'error',
                'payment_status' => $status,
                'msg'            => __('Payment has been declined, please try again later')
            ];
        }

        $creditTransaction = TopUpPaymentSuccess::make()->processSuccess($checkoutComPayment, $topUpPaymentApiPoint, $paymentAccountShop);

        return [
            'status'                => 'success',
            'credit_transaction_id' => $creditTransaction->id,
        ];
    }

    public function rules(): array
    {
        return [
            'cko-payment-id' => ['required', 'string'],
        ];
    }

    public function asController(TopUpPaymentApiPoint $topUpPaymentApiPoint, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($topUpPaymentApiPoint, $this->validatedData);
    }
}
