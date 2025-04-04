<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class PayInvoice extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Invoice $invoice, PaymentAccount $paymentAccount, array $modelData): Payment
    {
        $consolidateTotalPayments = Arr::get($invoice->shop->settings, 'consolidate_invoice_to_pay', true);
        if ($consolidateTotalPayments) {
            $amount = Arr::get($modelData, 'amount');
            $totalRefund = abs($invoice->refunds->where('in_process', false)->where('pay_status', InvoicePayStatusEnum::UNPAID)->sum('total_amount'));

            $calculateAmountInvoice = $amount + $invoice->payment_amount + $totalRefund;
            if ($calculateAmountInvoice >= $invoice->total_amount) {
                $modelData['amount'] = $calculateAmountInvoice - $invoice->payment_amount;
            }


            $payment = StorePayment::make()->action($invoice->customer, $paymentAccount, $modelData);

            AttachPaymentToInvoice::make()->action($invoice, $payment, []);

            $invoice->refresh();

            $refundsQuery = $invoice->refunds->where('in_process', false)->where('pay_status', InvoicePayStatusEnum::UNPAID);

            $needRefund = ($refundsQuery->sum('total_amount') - $refundsQuery->sum('payment_amount')) * -1;

            if ($needRefund > 0) {
                $amountRefund = min($needRefund, $invoice->payment_amount);

                if ($paymentAccount->type == PaymentAccountTypeEnum::ACCOUNT) {
                    RefundToInvoice::make()->action($invoice, $paymentAccount, [
                        'amount' => abs($amountRefund),
                        'type_refund' => 'credit',
                        'is_auto_refund' => true,
                    ]);
                } else {
                    RefundToInvoice::make()->action($invoice, $paymentAccount, [
                        'amount' => abs($amountRefund),
                        'original_payment_id' => $payment->id,
                        'type_refund' => 'payment',
                        'is_auto_refund' => true,
                    ]);
                }
            }

        } else {
            $payment = StorePayment::make()->action($invoice->customer, $paymentAccount, $modelData);

            AttachPaymentToInvoice::make()->action($invoice, $payment, []);
        }

        return $payment;
    }

    public function rules(): array
    {
        return [
            'amount'       => ['required', 'decimal:0,2'],
            'reference'    => ['nullable', 'string', 'max:255'],
            'status'       => ['sometimes', 'required', Rule::enum(PaymentStatusEnum::class)],
            'state'        => ['sometimes', 'required', Rule::enum(PaymentStateEnum::class)],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Invoice $invoice, PaymentAccount $paymentAccount, array $modelData): Payment
    {
        $this->initialisationFromShop($invoice->shop, $modelData);

        return $this->handle($invoice, $paymentAccount, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Invoice $invoice, PaymentAccount $paymentAccount, ActionRequest $request): void
    {
        $this->initialisationFromShop($invoice->shop, $request);

        $this->handle($invoice, $paymentAccount, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
