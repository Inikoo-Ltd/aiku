<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Aug 2025 12:11:37 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateCreditTransactions;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class RefundPayment extends OrgAction
{
    use WithActionUpdate;

    public function handle(Payment $payment, array $modelData): void
    {
        $maxToRefund = $payment->amount;
        $type        = Arr::get($modelData, 'type_refund', 'payment');
        $refundAmount = -abs(Arr::get($modelData, 'amount'));

        if ($payment->total_refund === $payment->refunds->sum('amount')) {
            return;
        }

        if (! blank($payment->invoices)) {
            $paymentAmount = $payment->invoices->sum('payment_amount');
            $totalAmount = $payment->invoices->sum('total_amount');

            $maxToRefund = $paymentAmount - $totalAmount;
        }

        $amountPayPerRefund = max($refundAmount, $maxToRefund);

        // TODO: Idk why the type after created still payment
        StorePayment::make()->action($payment->customer, $payment->paymentAccount, [
            'type' => PaymentTypeEnum::REFUND,
            'original_payment_id' => $payment->id,
            'amount' => abs($amountPayPerRefund)
        ]);

        if ($type === 'credit') {
            StoreCreditTransaction::make()->action($payment->customer, [
                'amount' => abs($amountPayPerRefund),
                'date'   => now(),
                'type'   => CreditTransactionTypeEnum::MONEY_BACK
            ]);
        }

        $totalRefund = $payment->total_refund + abs($amountPayPerRefund);
        $this->update($payment, [
            'total_refund' => $totalRefund
        ]);

        if ($payment->paymentAccount->type === PaymentAccountTypeEnum::CHECKOUT) {
            $ref = RefundPaymentApiRequest::run($payment);

            // TODO
            dd($ref);
        }

        if ($type === 'credit') {
            if ($this->asAction) {
                CustomerHydrateCreditTransactions::run($payment->customer);
            } else {
                CustomerHydrateCreditTransactions::dispatch($payment->customer);
            }
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("accounting.{$this->organisation->id}.edit");
    }

    public function asController(Organisation $organisation, Payment $payment, ActionRequest $request): void
    {
        $this->initialisation($organisation, $request);

        $this->handle($payment, $this->validatedData);
    }
}
