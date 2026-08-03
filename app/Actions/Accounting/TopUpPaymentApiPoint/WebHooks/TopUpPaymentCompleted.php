<?php

namespace App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks;

use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\RetinaWebhookAction;
use App\Enums\Accounting\TopUpPaymentApiPoint\TopUpPaymentApiPointStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\TopUpPaymentApiPoint;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class TopUpPaymentCompleted extends RetinaWebhookAction
{
    use WithCheckoutCom;

    public function handle(TopUpPaymentApiPoint $topUpPaymentApiPoint, array $modelData): array
    {
        if ($topUpPaymentApiPoint->state == TopUpPaymentApiPointStateEnum::SUCCESS) {
            return [
                'status'                => 'success',
                'credit_transaction_id' => Arr::get($topUpPaymentApiPoint->data, 'credit_transaction_id'),
            ];
        }

        $paymentAccountShop = PaymentAccountShop::find(Arr::get($topUpPaymentApiPoint->data, 'payment_account_shop_id.checkout'));
        if (!$paymentAccountShop) {
            return [
                'status'         => 'error',
                'payment_status' => 'Error',
                'msg'            => __('Payment account not found')
            ];
        }

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

        if (Arr::get($checkoutComPayment, 'metadata.api_point_ulid') != $topUpPaymentApiPoint->ulid) {
            return [
                'status'         => 'error',
                'payment_status' => $status,
                'msg'            => __('The payment does not belong to this top up.')
            ];
        }

        if (in_array($status, self::CHECKOUT_COM_FAILURE_STATUSES)) {
            TopUpPaymentFailure::make()->processFailure($topUpPaymentApiPoint, $checkoutComPayment);

            return [
                'status'         => 'error',
                'payment_status' => $status,
                'msg'            => __('Payment has been declined, please try again later')
            ];
        }

        if (in_array($status, self::CHECKOUT_COM_CAPTURED_STATUSES)) {
            $creditTransaction = TopUpPaymentSuccess::make()->processSuccess($checkoutComPayment, $topUpPaymentApiPoint, $paymentAccountShop);

            return [
                'status'                => 'success',
                'credit_transaction_id' => $creditTransaction?->id,
            ];
        }

        return [
            'status'         => 'pending',
            'payment_status' => $status,
            'msg'            => __('Payment is still pending, we will try again now')
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
