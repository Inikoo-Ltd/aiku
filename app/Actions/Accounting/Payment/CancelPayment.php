<?php

/*
 * author Louis Perez
 * created on 01-04-2026-15h-08m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\Payment\Traits\HydratesPaymentSideEffects;
use App\Actions\Accounting\Traits\AuthorizesAccountingEdit;
use App\Actions\Accounting\Invoice\UpdateInvoicePaymentState;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateCreditTransactions;
use App\Actions\CRM\Customer\UpdateBalanceCustomer;
use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Actions\OrgAction;
use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class CancelPayment extends OrgAction
{
    use HydratesPaymentSideEffects;
    use AuthorizesAccountingEdit;

    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Payment $payment): Payment
    {
        if ($payment->state === PaymentStateEnum::CANCELLED) {
            throw ValidationException::withMessages([
                'message' => __('Unable to cancel this payment as it is already cancelled.'),
            ]);
        }

        $payment->update([
            'state' => PaymentStateEnum::CANCELLED,
        ]);

        $originalPayment = $payment->originalPayment;

        if ($payment->type == PaymentTypeEnum::REFUND && $originalPayment) {
            // If the refund is cancelled. Original payment total refund will be updated
            $totalRefund = abs($originalPayment->refunds()->whereNot('state', PaymentStateEnum::CANCELLED->value)->sum('amount'));
            $originalPayment->update([
                'total_refund' => $totalRefund,
            ]);
        }

        if ($payment->paymentAccount->type === PaymentAccountTypeEnum::ACCOUNT) {
            // if cancel refund, should still return minus no? so this is correct. No need to modify the amount as it is already negative when it is a refund
            UpdateBalanceCustomer::make()->action($payment->customer, [
                'type'   => CreditTransactionTypeEnum::PAY_RETURN->value,
                'amount' => $payment->amount,
                'notes'  => __('Balance updated due to payment cancellation').": [Ref: $payment->reference]",
                'reason' => CreditTransactionReasonEnum::OTHER
            ]);
            CustomerHydrateCreditTransactions::run($payment->customer_id);
        }

        foreach ($payment->invoices as $invoice) {
            UpdateInvoicePaymentState::run($invoice);
        }

        foreach ($payment->orders as $order) {
            UpdateOrderPaymentsStatus::run($order);
        }

        $this->hydratePaymentSideEffects($payment);

        return $payment;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Payment $payment, ActionRequest $request): void
    {
        $this->hydratorsDelay = 0;
        $this->initialisationFromShop($payment->shop, $request);

        $this->handle($payment);
    }

}
