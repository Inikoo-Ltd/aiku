<?php

/*
 * author Louis Perez
 * created on 01-04-2026-15h-08m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\Invoice\UpdateInvoicePaymentState;
use App\Actions\Accounting\OrgPaymentServiceProvider\Hydrators\OrgPaymentServiceProviderHydratePayments;
use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydrateCustomers;
use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydratePayments;
use App\Actions\Accounting\PaymentServiceProvider\Hydrators\PaymentServiceProviderHydratePayments;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePayments;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateCreditTransactions;
use App\Actions\CRM\Customer\UpdateBalanceCustomer;
use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePayments;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePayments;
use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsCommand;

class CancelPayment extends OrgAction
{
    use AsCommand;

    public function handle(Payment $payment, array $modelData): Payment
    {
        if ($payment->state === PaymentStateEnum::CANCELLED) {
            throw ValidationException::withMessages([
                'message' => __('Unable to cancel this payment as it is already cancelled.'),
            ]);
        }

        $payment->update([
            'state' => PaymentStateEnum::CANCELLED,
        ]);

        if ($payment->type == PaymentTypeEnum::REFUND) {
            // If refund is cancelled. Original payment total refund will be updated
            $originalPayment = $payment->originalPayment;

            // This is correct, well since refund amount is minus
            $originalPayment->update([
                'total_refund' => $originalPayment->total_refund + $payment->amount,
            ]);
        };

        if ($payment->paymentAccount->type === PaymentAccountTypeEnum::ACCOUNT) {
            // if cancel refund, should still return minus no? so this is correct. No need to modify amount as it is already negative when it is a refund
            UpdateBalanceCustomer::make()->action($payment->customer, [
                'type'   => CreditTransactionTypeEnum::PAY_RETURN->value,
                'amount' => $payment->amount,
                'notes'  => __('Balance updated due to payment cancellation') . ": [Ref: {$payment->reference}]",
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

        GroupHydratePayments::dispatch($payment->group)->delay($this->hydratorsDelay);
        OrganisationHydratePayments::dispatch($payment->paymentAccount->organisation)->delay($this->hydratorsDelay);
        PaymentServiceProviderHydratePayments::dispatch($payment->paymentAccount->paymentServiceProvider)->delay($this->hydratorsDelay);
        PaymentAccountHydratePayments::dispatch($payment->paymentAccount)->delay($this->hydratorsDelay);
        PaymentAccountHydrateCustomers::dispatch($payment->paymentAccount)->delay($this->hydratorsDelay);
        ShopHydratePayments::dispatch($payment->shop)->delay($this->hydratorsDelay);
        OrgPaymentServiceProviderHydratePayments::dispatch($payment->orgPaymentServiceProvider)->delay($this->hydratorsDelay);

        return $payment;
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
        $this->hydratorsDelay = 0;
        $this->initialisationFromShop($payment->shop, $request);

        $this->handle($payment, $this->validatedData);
    }

}
