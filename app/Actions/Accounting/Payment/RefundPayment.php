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
use App\Enums\Accounting\Payment\PaymentClassEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Models\Accounting\Payment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class RefundPayment extends OrgAction
{
    use WithActionUpdate;


    private Payment $payment;

    public function handle(Payment $payment, array $modelData): Payment|array
    {
        $amountPayPerRefund = Arr::get($modelData, 'amount');
        $refundPayment = $this->processOnlineRefunds($payment, $amountPayPerRefund);

        if ($refundPayment->status !== PaymentStatusEnum::SUCCESS) {
            throw ValidationException::withMessages([
                'error' => true,
                'message' => __('We still waiting response from checkout.com')
            ]);
        }

        $totalRefund = abs($payment->total_refund) + abs($amountPayPerRefund);
        $this->update($payment, [
            'total_refund' => $totalRefund,
            'with_refund'  => true
        ]);

        if ($payment->class == PaymentClassEnum::TOPUP) {
            $this->processCreditTransactions($refundPayment, $refundPayment->amount);
        } else {
            $this->processInvoices($payment);
            $this->processOrders($payment);
        }
        return $refundPayment;
    }

    public function processInvoices(Payment $payment): void
    {
        if (!blank($payment->invoices)) {
            $paymentAmount = $payment->orders->sum('payment_amount');
            $totalAmount   = $payment->orders->sum('total_amount');

            // TODO
        }
    }

    public function processOrders(Payment $payment): void
    {
        if (!blank($payment->orders)) {
            $paymentAmount = $payment->invoices->sum('payment_amount');
            $totalAmount   = $payment->invoices->sum('total_amount');

            // TODO
        }
    }

    public function processCreditTransactions(Payment $refundPayment, float $amountPayPerRefund): void
    {
        StoreCreditTransaction::make()->action($refundPayment->customer, [
            'payment_id' => $refundPayment->id,
            'amount' => abs($amountPayPerRefund),
            'date'   => now(),
            'type'   => CreditTransactionTypeEnum::MONEY_BACK
        ]);

        if ($this->asAction) {
            CustomerHydrateCreditTransactions::run($refundPayment->customer);
        } else {
            CustomerHydrateCreditTransactions::dispatch($refundPayment->customer);
        }
    }

    public function processOnlineRefunds(Payment $payment, $amount): Payment
    {
        return RefundPaymentCheckoutCom::run($payment, $amount);
    }

    public function htmlResponse(Payment $refundPayment): Response
    {
        return Inertia::location(route('grp.org.accounting.payments.show', [
            'organisation' => $refundPayment->organisation->slug,
            'payment' => $refundPayment->id
        ]));
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric','gt:0','lte:'.$this->payment->amount - $this->payment->total_refund],
            'reason' => ['required', 'string', 'max:1000']
        ];
    }

    public function asController(Organisation $organisation, Payment $payment, ActionRequest $request): Payment
    {
        $this->payment = $payment;
        $this->initialisation($organisation, $request);

        return $this->handle($payment, $this->validatedData);
    }
}
