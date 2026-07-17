<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 19:14:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrderPaymentApiPoint\WebHooks;

use App\Actions\Accounting\OrderPaymentApiPoint\UpdateOrderPaymentApiPoint;
use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\RetinaWebhookAction;
use App\Enums\Accounting\OrderPaymentApiPoint\OrderPaymentApiPointStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class CheckoutComOrderPaymentFailure extends RetinaWebhookAction
{
    use WithCheckoutCom;

    public function handle(OrderPaymentApiPoint $orderPaymentApiPoint, array $modelData)
    {
        if ($orderPaymentApiPoint->state == OrderPaymentApiPointStateEnum::SUCCESS) {
            return $orderPaymentApiPoint;
        }

        $paymentAccountShopID = Arr::get($orderPaymentApiPoint->data, 'payment_methods.checkout');
        $paymentAccountShop   = PaymentAccountShop::find($paymentAccountShopID);

        if (!$paymentAccountShop) {
            return $this->processError($orderPaymentApiPoint, ['error' => true, 'message' => 'Payment account not found']);
        }

        $checkoutComPayment = $this->getCheckOutPayment(
            $paymentAccountShop,
            Arr::get($modelData, 'cko-payment-id', '')
        );

        if (Arr::get($checkoutComPayment, 'error')) {
            return $this->processError($orderPaymentApiPoint, $checkoutComPayment);
        }

        if (in_array(Arr::get($checkoutComPayment, 'status'), self::CHECKOUT_COM_CAPTURED_STATUSES)
            && Arr::get($checkoutComPayment, 'metadata.api_point_id') == $orderPaymentApiPoint->id
        ) {
            CheckoutComOrderPaymentSuccess::make()->processSuccessfulPayment($orderPaymentApiPoint, $paymentAccountShop, $checkoutComPayment);

            return $orderPaymentApiPoint->refresh();
        }

        return $this->processFailure($orderPaymentApiPoint, $checkoutComPayment);
    }

    public function processFailure(OrderPaymentApiPoint $orderPaymentApiPoint, array $checkoutComPayment)
    {
        return DB::transaction(function () use ($orderPaymentApiPoint, $checkoutComPayment) {
            /** @var OrderPaymentApiPoint $orderPaymentApiPoint locked so a racing capture webhook committing SUCCESS is never overwritten */
            $orderPaymentApiPoint = OrderPaymentApiPoint::lockForUpdate()->find($orderPaymentApiPoint->id);

            if ($orderPaymentApiPoint->state == OrderPaymentApiPointStateEnum::SUCCESS) {
                return $orderPaymentApiPoint;
            }

            return UpdateOrderPaymentApiPoint::run(
                $orderPaymentApiPoint,
                [
                    'state'        => OrderPaymentApiPointStateEnum::FAILURE,
                    'processed_at' => now(),
                    'data'         => [
                        'payment' => Arr::except($checkoutComPayment, ['http_metadata', '_links'])
                    ]

                ]
            );
        });
    }


    public function rules(): array
    {
        return [
            'cko-payment-session-id' => ['sometimes', 'string'],
            'cko-session-id'         => ['sometimes', 'string'],
            'cko-payment-id'         => ['sometimes', 'string'],
        ];
    }

    public function asController(OrderPaymentApiPoint $orderPaymentApiPoint, ActionRequest $request): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        $this->initialisation($request);
        $orderPaymentApiPoint = $this->handle($orderPaymentApiPoint, $this->validatedData);

        if ($orderPaymentApiPoint->state == OrderPaymentApiPointStateEnum::SUCCESS) {
            $notification = [
                'status'  => 'success',
                'title'   => __('Payment received'),
                'message' => __('Your payment was received and your order has been submitted.'),
            ];
        } elseif ($orderPaymentApiPoint->state == OrderPaymentApiPointStateEnum::ERROR) {
            $notification = [
                'status' => 'error',
                'title'  => __('Network Error, please try again'),
            ];
        } else {
            $notification = [
                'status'  => 'failure',
                'title'   => $this->getFailureTitle($orderPaymentApiPoint->failure_status),
                'message' => $this->getFailureMessage($orderPaymentApiPoint->failure_status),
            ];
        }


        $shop = $orderPaymentApiPoint->order->shop;

        if ($shop->type == ShopTypeEnum::DROPSHIPPING) {
            return Redirect::route('retina.dropshipping.checkout.show', [$orderPaymentApiPoint->order->slug])->with(
                'modal',
                $notification
            );
        } else {
            return Redirect::route('retina.ecom.checkout.show')->with(
                'modal',
                $notification
            );
        }
    }

    private function processError(OrderPaymentApiPoint $orderPaymentApiPoint, array $payment): OrderPaymentApiPoint
    {
        return UpdateOrderPaymentApiPoint::run(
            $orderPaymentApiPoint,
            [
                'state'        => OrderPaymentApiPointStateEnum::ERROR,
                'processed_at' => now(),
                'data'         => [
                    'error' => Arr::except($payment, ['error'])
                ]

            ]
        );
    }

}
