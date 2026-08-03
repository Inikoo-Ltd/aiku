<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 17 Jul 2026 15:18:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrderPaymentApiPoint\WebHooks;

use App\Actions\Accounting\OrderPaymentApiPoint\UpdateOrderPaymentApiPoint;
use App\Actions\Accounting\Payment\PastPay\WithPastpayConfiguration;
use App\Actions\RetinaWebhookAction;
use App\Enums\Accounting\OrderPaymentApiPoint\OrderPaymentApiPointStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Sentry;

class PastpayOrderPaymentFailure extends RetinaWebhookAction
{
    use WithPastpayConfiguration;

    /**
     * @throws \Throwable
     */
    public function handle(OrderPaymentApiPoint $orderPaymentApiPoint): OrderPaymentApiPoint
    {
        if ($orderPaymentApiPoint->state == OrderPaymentApiPointStateEnum::SUCCESS) {
            return $orderPaymentApiPoint;
        }

        $paymentAccountShop = PaymentAccountShop::find(
            Arr::get($orderPaymentApiPoint->data, 'pastpay.payment_account_shop_id')
        );

        if (!$paymentAccountShop) {
            return $this->processError($orderPaymentApiPoint, ['message' => 'PastPay payment account not found']);
        }

        $this->paymentAccount = $paymentAccountShop->paymentAccount;

        try {
            $pastpayOrder = $this->pastpayGetOrder($orderPaymentApiPoint->order);
        } catch (\Exception $e) {
            Sentry::captureException($e);

            return $this->processError($orderPaymentApiPoint, ['message' => $e->getMessage()]);
        }

        if (in_array(Arr::get($pastpayOrder, 'data.status'), PastpayOrderPaymentSuccess::PASTPAY_APPROVED_STATUSES)) {
            PastpayOrderPaymentSuccess::make()->processApprovedOrder($orderPaymentApiPoint, $paymentAccountShop, $pastpayOrder);

            return $orderPaymentApiPoint->refresh();
        }

        return $this->processFailure($orderPaymentApiPoint, $pastpayOrder);
    }

    public function processFailure(OrderPaymentApiPoint $orderPaymentApiPoint, array $pastpayOrder): OrderPaymentApiPoint
    {
        return DB::transaction(function () use ($orderPaymentApiPoint, $pastpayOrder) {
            /** @var OrderPaymentApiPoint $orderPaymentApiPoint locked so a racing success redirect committing SUCCESS is never overwritten */
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
                        'payment' => Arr::get($pastpayOrder, 'data', []),
                    ]
                ]
            );
        });
    }

    private function processError(OrderPaymentApiPoint $orderPaymentApiPoint, array $error): OrderPaymentApiPoint
    {
        return UpdateOrderPaymentApiPoint::run(
            $orderPaymentApiPoint,
            [
                'state'        => OrderPaymentApiPointStateEnum::ERROR,
                'processed_at' => now(),
                'data'         => [
                    'error' => $error,
                ]
            ]
        );
    }

    /**
     * @throws \Throwable
     */
    public function asController(OrderPaymentApiPoint $orderPaymentApiPoint, ActionRequest $request): \Illuminate\Http\RedirectResponse
    {
        $this->initialisation($request);
        $orderPaymentApiPoint = $this->handle($orderPaymentApiPoint);

        if ($orderPaymentApiPoint->state == OrderPaymentApiPointStateEnum::SUCCESS) {
            $notification = [
                'status'  => 'success',
                'title'   => __('Payment received'),
                'message' => __('Your payment was received and your order has been submitted.'),
            ];
        } else {
            $notification = [
                'status'  => 'failure',
                'title'   => __('Payment not completed'),
                'message' => __('Your PastPay payment was not completed. Please try again or choose another payment method.'),
            ];
        }

        $shop = $orderPaymentApiPoint->order->shop;

        if ($shop->type == ShopTypeEnum::DROPSHIPPING) {
            return Redirect::route('retina.dropshipping.checkout.show', [$orderPaymentApiPoint->order->slug])->with(
                'modal',
                $notification
            );
        }

        return Redirect::route('retina.ecom.checkout.show')->with(
            'modal',
            $notification
        );
    }
}
