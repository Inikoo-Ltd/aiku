<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\OrgAction;
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
            $totalRefund = abs($invoice->refunds->sum('total_amount'));
            $needRefund = $totalRefund - abs($invoice->refunds->sum('payment_amount'));

            $calculateAmountInvoice = $amount + $totalRefund;
            if ($calculateAmountInvoice >= $invoice->total_amount) {
                $modelData['amount'] = $calculateAmountInvoice;
            }

            $payment = StorePayment::make()->action($invoice->customer, $paymentAccount, $modelData);

            AttachPaymentToInvoice::make()->action($invoice, $payment, []);

            // payback refund
            if ($needRefund > 0 && $amount > $needRefund) {
                if ($paymentAccount->type == PaymentAccountTypeEnum::ACCOUNT) {
                    RefundToCredit::make()->action($invoice, [
                        'amount' => $needRefund,
                    ]);
                } else {
                    RefundToPaymentAccount::make()->action($invoice, $paymentAccount, [
                        'amount' => $needRefund,
                        'original_payment_id' => $payment->id,
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
