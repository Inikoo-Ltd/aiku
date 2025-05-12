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
use App\Actions\OrgAction;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\TopUp\TopUpStatusEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\TopUpPaymentApiPoint;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class TopUpPaymentSuccess extends OrgAction
{
    use AsAction;
    use WithCheckoutCom;

    public function handle(TopUpPaymentApiPoint $topUpPaymentApiPoint, array $modelData)
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


        $payment = StorePayment::make()->action(
            $topUpPaymentApiPoint->customer,
            $paymentAccountShop->paymentAccount,
            $paymentData
        );


        $topUp = StoreTopUp::make()->action(
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
        ];

        $creditTransaction = StoreCreditTransaction::make()->action(
            $topUpPaymentApiPoint->customer,
            $creditTransactionData
        );

        dd($payment, $creditTransaction);
    }

    public function rules(): array
    {
        return [
            'cko-payment-session-id' => ['sometimes', 'string'],
            'cko-session-id'         => ['sometimes', 'string'],
            'cko-payment-id'         => ['sometimes', 'string'],
        ];
    }

    public function asController(TopUpPaymentApiPoint $topUpPaymentApiPoint, ActionRequest $request)
    {
        $this->initialisation($topUpPaymentApiPoint->organisation, $request);
        $this->handle($topUpPaymentApiPoint, $this->validatedData);
    }

}
