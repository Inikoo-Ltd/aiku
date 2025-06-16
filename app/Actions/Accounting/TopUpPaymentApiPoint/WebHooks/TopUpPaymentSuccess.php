<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:32:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\TopUp\StoreTopUp;
use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\RetinaWebhookAction;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\TopUp\TopUpStatusEnum;
use App\Http\Resources\Accounting\TopUpResource;
use App\Models\Accounting\CreditTransaction;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\TopUpPaymentApiPoint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class TopUpPaymentSuccess extends RetinaWebhookAction
{
    use WithCheckoutCom;

    public function handle(TopUpPaymentApiPoint $topUpPaymentApiPoint, array $modelData): CreditTransaction
    {
        $paymentAccountShopId = Arr::get($topUpPaymentApiPoint->data, 'payment_account_shop_id');
        $paymentAccountShop   = PaymentAccountShop::find($paymentAccountShopId)->first();

        $checkoutComPayment = $this->getCheckOutPayment(
            $paymentAccountShop,
            $modelData['cko-payment-id']
        );

        $amount = Arr::get($checkoutComPayment, 'amount', 0) / 100;

        $paymentData = [
            'reference'               => Arr::get($checkoutComPayment, 'id'),
            'amount'                  => $amount,
            'status'                  => PaymentStatusEnum::SUCCESS,
            'state'                   => PaymentStateEnum::COMPLETED,
            'type'                    => PaymentTypeEnum::PAYMENT,
            'payment_account_shop_id' => $paymentAccountShop->id,
            'api_point_type'          => class_basename($topUpPaymentApiPoint),
            'api_point_id'            => $topUpPaymentApiPoint->id,

        ];


        $payment = StorePayment::run(
            $topUpPaymentApiPoint->customer,
            $paymentAccountShop->paymentAccount,
            $paymentData
        );


        $topUp = StoreTopUp::run(
            $payment,
            [
                'amount' => $amount,
                'status' => TopUpStatusEnum::SUCCESS
            ]
        );

        $creditTransactionData = [
            'amount'     => $amount,
            'payment_id' => $payment->id,
            'top_up_id'  => $topUp->id,
            'type'       => CreditTransactionTypeEnum::TOP_UP,
        ];


        return StoreCreditTransaction::run(
            $topUpPaymentApiPoint->customer,
            $creditTransactionData
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

        $creditTransaction = $this->handle($topUpPaymentApiPoint, $this->validatedData);


        return Redirect::route('retina.top_up.dashboard')->with(
            'notification',
            [
                'status'  => 'success',
                'title'   => __('Success!'),
                'message' => __('Top up payment has been successfully processed.'),
                'top_up'  => TopUpResource::make($creditTransaction->topUp)
            ]
        );
    }

}
