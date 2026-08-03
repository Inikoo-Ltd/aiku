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
use App\Actions\Accounting\TopUpPaymentApiPoint\UpdateTopUpPaymentApiPoint;
use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateTopUps;
use App\Actions\RetinaWebhookAction;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentClassEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\TopUp\TopUpStatusEnum;
use App\Enums\Accounting\TopUpPaymentApiPoint\TopUpPaymentApiPointStateEnum;
use App\Http\Resources\Accounting\TopUpResource;
use App\Models\Accounting\CreditTransaction;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\TopUpPaymentApiPoint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class TopUpPaymentSuccess extends RetinaWebhookAction
{
    use WithCheckoutCom;

    public function handle(TopUpPaymentApiPoint $topUpPaymentApiPoint, array $modelData): array
    {
        if ($topUpPaymentApiPoint->state == TopUpPaymentApiPointStateEnum::SUCCESS) {
            return [
                'status'             => 'success',
                'credit_transaction' => CreditTransaction::find(Arr::get($topUpPaymentApiPoint->data, 'credit_transaction_id')),
            ];
        }

        $paymentAccountShop = PaymentAccountShop::find(Arr::get($topUpPaymentApiPoint->data, 'payment_account_shop_id.checkout'));
        if (!$paymentAccountShop) {
            return [
                'status' => 'pending',
            ];
        }

        $checkoutComPayment = $this->getCheckOutPayment(
            $paymentAccountShop,
            Arr::get($modelData, 'cko-payment-id', '')
        );

        $status = Arr::get($checkoutComPayment, 'status');

        if (!Arr::get($checkoutComPayment, 'error') && Arr::get($checkoutComPayment, 'metadata.api_point_ulid') != $topUpPaymentApiPoint->ulid) {
            return [
                'status' => 'failure',
            ];
        }

        if (in_array($status, self::CHECKOUT_COM_FAILURE_STATUSES)) {
            TopUpPaymentFailure::make()->processFailure($topUpPaymentApiPoint, $checkoutComPayment);

            return [
                'status' => 'failure',
            ];
        }

        if (in_array($status, self::CHECKOUT_COM_CAPTURED_STATUSES)) {
            return [
                'status'             => 'success',
                'credit_transaction' => $this->processSuccess($checkoutComPayment, $topUpPaymentApiPoint, $paymentAccountShop),
            ];
        }

        /** Anything else (API error, Pending, Authorized-before-capture, unknown) waits for the
         * capture webhook: money must be captured before the balance is credited */
        return [
            'status' => 'pending',
        ];
    }

    public function processSuccess($checkoutComPayment, $topUpPaymentApiPoint, $paymentAccountShop): ?CreditTransaction
    {
        $creditTransaction = DB::transaction(function () use ($checkoutComPayment, $topUpPaymentApiPoint, $paymentAccountShop) {
            /** @var TopUpPaymentApiPoint $topUpPaymentApiPoint locked to stop the client callback, the redirect and the webhook processing the same payment twice */
            $topUpPaymentApiPoint = TopUpPaymentApiPoint::lockForUpdate()->find($topUpPaymentApiPoint->id);

            $existingPayment = Payment::where('payment_account_shop_id', $paymentAccountShop->id)
                ->where('reference', Arr::get($checkoutComPayment, 'id'))
                ->first();

            if ($existingPayment) {
                $existingCreditTransaction = CreditTransaction::where('payment_id', $existingPayment->id)->first();

                if ($existingCreditTransaction && $topUpPaymentApiPoint->state != TopUpPaymentApiPointStateEnum::SUCCESS) {
                    UpdateTopUpPaymentApiPoint::run(
                        $topUpPaymentApiPoint,
                        [
                            'state'        => TopUpPaymentApiPointStateEnum::SUCCESS,
                            'processed_at' => now(),
                            'data'         => [
                                'payment_id'            => $existingPayment->id,
                                'top_up_id'             => $existingCreditTransaction->top_up_id,
                                'credit_transaction_id' => $existingCreditTransaction->id,
                            ]
                        ]
                    );
                }

                return $existingCreditTransaction;
            }

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
                'class'                   => PaymentClassEnum::TOPUP,
                'source'                  => Arr::get($checkoutComPayment, 'source'),
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


            $creditTransaction = StoreCreditTransaction::run(
                $topUpPaymentApiPoint->customer,
                $creditTransactionData
            );

            UpdateTopUpPaymentApiPoint::run(
                $topUpPaymentApiPoint,
                [
                    'state'        => TopUpPaymentApiPointStateEnum::SUCCESS,
                    'processed_at' => now(),
                    'data'         => [
                        'payment_id'            => $payment->id,
                        'top_up_id'             => $topUp->id,
                        'credit_transaction_id' => $creditTransaction->id,
                    ]

                ]
            );

            return $creditTransaction;
        });

        CustomerHydrateTopUps::dispatch($topUpPaymentApiPoint->customer_id);

        return $creditTransaction;
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

        $result = $this->handle($topUpPaymentApiPoint, $this->validatedData);

        if (Arr::get($result, 'status') == 'pending') {
            return Redirect::route('retina.top_up.checkout', [$topUpPaymentApiPoint->id])
                ->with('pending_cko_payment_id', Arr::get($this->validatedData, 'cko-payment-id'));
        }

        if (Arr::get($result, 'status') == 'failure') {
            return TopUpPaymentFailure::make()->redirectAfterFailure($topUpPaymentApiPoint->refresh());
        }

        $creditTransaction = Arr::get($result, 'credit_transaction');

        if (!$creditTransaction) {
            return Redirect::route('retina.top_up.dashboard')->with(
                'notification',
                [
                    'status'  => 'success',
                    'title'   => __('Payment received'),
                    'message' => __('Your payment was received and your balance has been topped up.'),
                ]
            );
        }

        return Redirect::route('retina.top_up.dashboard')->with(
            'notification',
            [
                'status'  => 'success',
                'title'   => __('Success!'),
                'message' => __('Top up balance :amount has been successfully processed.', [
                    'amount' => $creditTransaction->amount
                ]),
                'top_up'  => TopUpResource::make($creditTransaction->topUp)
            ]
        );
    }
}
