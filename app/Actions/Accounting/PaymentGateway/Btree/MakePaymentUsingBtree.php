<?php

namespace App\Actions\Accounting\PaymentGateway\Btree;

use App\Actions\Accounting\Payment\UpdatePayment;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Models\Accounting\Payment;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class MakePaymentUsingBtree
{
    use AsAction;
    use WithActionUpdate;

    public const array SUCCESS_TRANSACTION_STATUSES = [
        'AUTHORIZED',
        'SETTLED',
        'SETTLEMENT_CONFIRMED',
        'SETTLEMENT_PENDING',
        'SETTLING',
        'SUBMITTED_FOR_SETTLEMENT',
    ];

    public function handle(Payment $payment, array $modelData): Payment
    {
        $transaction = ChargeBtreePaymentMethod::run(
            $payment->paymentAccount,
            Arr::get($modelData, 'payment_method_nonce'),
            (string)$payment->amount,
            Arr::get($modelData, 'transaction', [])
        );

        $isSuccessful = in_array(Arr::get($transaction, 'status'), self::SUCCESS_TRANSACTION_STATUSES);

        return UpdatePayment::run($payment, [
            'reference' => Arr::get($transaction, 'legacyId', Arr::get($transaction, 'id')),
            'status'    => $isSuccessful ? PaymentStatusEnum::SUCCESS : PaymentStatusEnum::FAIL,
            'state'     => $isSuccessful ? PaymentStateEnum::COMPLETED : PaymentStateEnum::ERROR,
            'data'      => [
                'btree' => $transaction
            ]
        ]);
    }
}
