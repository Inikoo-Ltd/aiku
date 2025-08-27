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
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class RefundPayment extends OrgAction
{
    use WithActionUpdate;


    private Payment $payment;

    public function handle(Payment $payment, array $modelData): Payment
    {
        $amountPayPerRefund = Arr::get($modelData, 'amount');

        $refundPayment = StorePayment::make()->action($payment->customer, $payment->paymentAccount, [
            'type'                    => PaymentTypeEnum::REFUND,
            'original_payment_id'     => $payment->id,
            'amount'                  => -abs($amountPayPerRefund),
            'payment_account_shop_id' => $payment->payment_account_shop_id
        ]);

        $totalRefund = abs($payment->total_refund) + abs($amountPayPerRefund);

        $this->update($payment, [
            'total_refund' => $totalRefund,
            'with_refund'  => true
        ]);


        $this->processInvoices($payment);
        $this->processOrders($payment);
        $this->processOnlineRefunds($payment, $refundPayment);

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

    public function processCreditTransactions(Payment $payment, float $amountPayPerRefund): void
    {
        if ($payment->class === PaymentClassEnum::TOPUP) {
            StoreCreditTransaction::make()->action($payment->customer, [
                'amount' => abs($amountPayPerRefund),
                'date'   => now(),
                'type'   => CreditTransactionTypeEnum::MONEY_BACK
            ]);

            if ($this->asAction) {
                CustomerHydrateCreditTransactions::run($payment->customer);
            } else {
                CustomerHydrateCreditTransactions::dispatch($payment->customer);
            }
        }
    }

    public function processOnlineRefunds(Payment $payment, Payment $refundPayment): void
    {
        if ($payment->paymentAccount->type === PaymentAccountTypeEnum::CHECKOUT) {
            $ref = RefundPaymentApiRequest::run($refundPayment, $payment->reference);

            if (!Arr::get($ref, 'error')) {
                $this->update($refundPayment, [
                    'state'  => PaymentStateEnum::COMPLETED,
                    'status' => PaymentStatusEnum::SUCCESS
                ]);
            }
        }
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
