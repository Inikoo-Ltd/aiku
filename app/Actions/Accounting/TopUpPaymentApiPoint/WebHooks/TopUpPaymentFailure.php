<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:32:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks;

use App\Actions\Accounting\TopUpPaymentApiPoint\StoreTopUpPaymentApiPoint;
use App\Actions\Accounting\TopUpPaymentApiPoint\UpdateTopUpPaymentApiPoint;
use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\RetinaWebhookAction;
use App\Enums\Accounting\TopUpPaymentApiPoint\TopUpPaymentApiPointStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\TopUpPaymentApiPoint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class TopUpPaymentFailure extends RetinaWebhookAction
{
    use WithCheckoutCom;
    use WithCheckoutComTopUpWebhook;

    public function handle(TopUpPaymentApiPoint $topUpPaymentApiPoint, array $modelData)
    {
        $paymentAccountShopID = Arr::get($topUpPaymentApiPoint->data, 'payment_account_shop_id.checkout');
        $paymentAccountShop   = PaymentAccountShop::find($paymentAccountShopID);

        $payment = $this->getCheckOutPayment(
            $paymentAccountShop,
            $modelData['cko-payment-id']
        );


        if (Arr::get($payment, 'error')) {
            return $this->processError($topUpPaymentApiPoint, $payment);
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
        $topUpPaymentApiPoint = $this->handle($topUpPaymentApiPoint, $this->validatedData);


        if ($topUpPaymentApiPoint->state == TopUpPaymentApiPointStateEnum::ERROR) {
            $notification = [
                'status' => 'error',
                'title'  => __('Network Error, please try again'),
            ];
        } else {
            $notification = [
                'status'  => 'failure',
                'title'   => $this->getFailureTitle($topUpPaymentApiPoint->failure_status),
                'message' => $this->getFailureMessage($topUpPaymentApiPoint->failure_status),
            ];
        }


        $newToUpPaymentApiPoint = StoreTopUpPaymentApiPoint::run(
            $topUpPaymentApiPoint->customer,
            [
                'amount' => $topUpPaymentApiPoint->amount,
            ]
        );


        return Redirect::route('retina.top_up.checkout', [$newToUpPaymentApiPoint])->with(
            'notification',
            $notification
        );
    }

}
