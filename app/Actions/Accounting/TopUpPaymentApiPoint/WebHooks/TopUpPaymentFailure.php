<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:32:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks;

use App\Actions\Accounting\TopUpPaymentApiPoint\StoreTopUpPaymentApiPoint;
use App\Actions\Accounting\Payment\CheckoutCom\StoreFailedCheckoutComPayment;
use App\Actions\Accounting\TopUpPaymentApiPoint\UpdateTopUpPaymentApiPoint;
use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\RetinaWebhookAction;
use App\Enums\Accounting\TopUpPaymentApiPoint\TopUpPaymentApiPointStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\TopUpPaymentApiPoint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Sentry;

class TopUpPaymentFailure extends RetinaWebhookAction
{
    use WithCheckoutCom;
    use WithCheckoutComTopUpWebhook;

    public function handle(TopUpPaymentApiPoint $topUpPaymentApiPoint, array $modelData): TopUpPaymentApiPoint
    {
        if ($topUpPaymentApiPoint->state == TopUpPaymentApiPointStateEnum::SUCCESS) {
            return $topUpPaymentApiPoint;
        }

        $paymentAccountShopID = Arr::get($topUpPaymentApiPoint->data, 'payment_account_shop_id.checkout');
        $paymentAccountShop   = PaymentAccountShop::find($paymentAccountShopID);

        if (!$paymentAccountShop) {
            return $this->processError($topUpPaymentApiPoint, ['error' => true, 'message' => 'Payment account not found']);
        }

        $payment = $this->getCheckOutPayment(
            $paymentAccountShop,
            Arr::get($modelData, 'cko-payment-id', '')
        );


        if (Arr::get($payment, 'error')) {
            return $this->processError($topUpPaymentApiPoint, $payment);
        }

        if (in_array(Arr::get($payment, 'status'), self::CHECKOUT_COM_CAPTURED_STATUSES)
            && Arr::get($payment, 'metadata.api_point_ulid') == $topUpPaymentApiPoint->ulid
        ) {
            TopUpPaymentSuccess::make()->processSuccess($payment, $topUpPaymentApiPoint, $paymentAccountShop);

            return $topUpPaymentApiPoint->refresh();
        }

        return $this->processFailure($topUpPaymentApiPoint, $payment);
    }

    public function processFailure(TopUpPaymentApiPoint $topUpPaymentApiPoint, $payment, ?string $eventType = null): TopUpPaymentApiPoint
    {
        $this->recordFailedAttempt($topUpPaymentApiPoint, (array) $payment, $eventType);

        return DB::transaction(function () use ($topUpPaymentApiPoint, $payment) {
            /** @var TopUpPaymentApiPoint $topUpPaymentApiPoint locked so a racing capture webhook committing SUCCESS is never overwritten */
            $topUpPaymentApiPoint = TopUpPaymentApiPoint::lockForUpdate()->find($topUpPaymentApiPoint->id);

            if ($topUpPaymentApiPoint->state == TopUpPaymentApiPointStateEnum::SUCCESS) {
                return $topUpPaymentApiPoint;
            }

            return UpdateTopUpPaymentApiPoint::run(
                $topUpPaymentApiPoint,
                [
                    'state'        => TopUpPaymentApiPointStateEnum::FAILURE,
                    'processed_at' => now(),
                    'data'         => [
                        'payment' => Arr::except($payment, ['http_metadata', '_links'])
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

    public function asController(TopUpPaymentApiPoint $topUpPaymentApiPoint, ActionRequest $request): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        $this->initialisation($request);
        /** @var TopUpPaymentApiPoint $topUpPaymentApiPoint */
        $topUpPaymentApiPoint = $this->handle($topUpPaymentApiPoint, $this->validatedData);

        if ($topUpPaymentApiPoint->state == TopUpPaymentApiPointStateEnum::SUCCESS) {
            return Redirect::route('retina.top_up.dashboard')->with(
                'notification',
                [
                    'status'  => 'success',
                    'title'   => __('Payment received'),
                    'message' => __('Your payment was received and your balance has been topped up.'),
                ]
            );
        }

        return $this->redirectAfterFailure($topUpPaymentApiPoint);
    }

    public function redirectAfterFailure(TopUpPaymentApiPoint $topUpPaymentApiPoint): \Illuminate\Http\RedirectResponse
    {
        if ($topUpPaymentApiPoint->state == TopUpPaymentApiPointStateEnum::ERROR) {
            $notification = [
                'status' => 'error',
                'title'  => __('Network Error, please try again'),
            ];
        } else {
            $failureStatus = Arr::get($topUpPaymentApiPoint->data, 'payment.status');
            $notification  = [
                'status'  => 'failure',
                'title'   => $this->getFailureTitle($failureStatus),
                'message' => $this->getFailureMessage($failureStatus),
            ];
        }


        $newToUpPaymentApiPoint = StoreTopUpPaymentApiPoint::run(
            $topUpPaymentApiPoint->customer,
            [
                'amount' => $topUpPaymentApiPoint->amount,
            ]
        );


        return Redirect::route('retina.top_up.checkout', [$newToUpPaymentApiPoint])->with(
            'modal',
            $notification
        );
    }

    /**
     * Best effort: the failed attempt is for the report, it must never get in the way of
     * telling the customer their top up did not go through.
     */
    private function recordFailedAttempt(TopUpPaymentApiPoint $topUpPaymentApiPoint, array $checkoutComPayment, ?string $eventType): void
    {
        try {
            $paymentAccountShop = PaymentAccountShop::find(Arr::get($topUpPaymentApiPoint->data, 'payment_account_shop_id.checkout'));
            $customer           = $topUpPaymentApiPoint->customer;
            if ($paymentAccountShop && $customer) {
                StoreFailedCheckoutComPayment::run($customer, $paymentAccountShop, $checkoutComPayment, $eventType, [
                    'type' => class_basename($topUpPaymentApiPoint),
                    'id'   => $topUpPaymentApiPoint->id,
                ]);
            }
        } catch (\Throwable $e) {
            Sentry::captureException($e);
        }
    }
}
