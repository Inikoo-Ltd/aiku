<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\OrgAction;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use Illuminate\Support\Arr;

class AttachPaymentToInvoice extends OrgAction
{
    public function handle(Invoice $invoice, Payment $payment, array $modelData): void
    {
        SetInvoicePaymentState::run($invoice);
        $paymentAmount = Arr::get($modelData, 'amount', $payment->amount);
        $toPay = $invoice->total_amount - $invoice->payment_amount;

        $amountToCredit = 0;
        $amount = $paymentAmount;

        if (!$invoice->original_invoice_id) {
            if ($paymentAmount > $toPay) {
                $amount = $toPay;
                $amountToCredit = $paymentAmount - $toPay;
            } else {
                $amount = $paymentAmount;
            }
        }

        $invoice->payments()->attach($payment, [
            'amount' => $amount,
        ]);

        if ($amountToCredit != 0) {

            StoreCreditTransaction::make()->action($invoice->customer, [
                'amount' => $amountToCredit,
                'type' => CreditTransactionTypeEnum::FROM_EXCESS,
                'payment_id' => $payment->id,
                'date' => now()
            ]);
        }

        SetInvoicePaymentState::run($invoice);
    }

    public function rules(): array
    {
        return [
            'amount'    => ['sometimes', 'numeric'],
        ];
    }

    public function action(Invoice $invoice, Payment $payment, array $modelData): void
    {
        $this->asAction = true;
        $this->initialisationFromShop($invoice->shop, $modelData);
        $this->handle($invoice, $payment, $modelData);
    }
}
