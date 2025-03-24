<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 21-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateCreditTransactions;
use App\Actions\OrgAction;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\PaymentAccount;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class RefundToInvoice extends OrgAction
{
    public function handle(Invoice $invoice, PaymentAccount $paymentAccount, array $modelData): Invoice
    {
        $type = Arr::get($modelData, 'type', 'payment');
        $totalToPay = -abs(Arr::get($modelData, 'amount'));
        $totalToPayRound = round($totalToPay, 2);


        $refunds = $invoice->refunds->where('in_process', false)->where('pay_status', InvoicePayStatusEnum::UNPAID)->sortByDesc('total_amount')->all();
        $totalRefund = $invoice->refunds->where('in_process', false)->where('pay_status', InvoicePayStatusEnum::UNPAID)->sum('total_amount');

        if ($totalToPayRound < round($totalRefund, 2)) {
            throw ValidationException::withMessages(
                [
                    'message' => [
                        'amount' => 'The refund amount exceeds the total amount that should be refund',
                    ]
                ]
            );
        }

        foreach ($refunds as $refund) {
            if ($totalRefund >= 0 || $totalToPay >= 0) {
                break;
            }

            $amountPayPerRefund = max($totalToPay, $refund->total_amount);

            $paymentInRefund = StorePayment::make()->action($invoice->customer, $paymentAccount, [
                'amount' => $amountPayPerRefund,
                'status' => PaymentStatusEnum::SUCCESS->value,
                'state' => PaymentStateEnum::COMPLETED->value,
            ]);

            // for invoice refund
            AttachPaymentToInvoice::make()->action($refund, $paymentInRefund, []);

            if ($type === 'credit') {
                StoreCreditTransaction::make()->action($invoice->customer, [
                    'amount' => abs($amountPayPerRefund),
                    'date'  => now(),
                    'type' => CreditTransactionTypeEnum::MONEY_BACK
                ]);
            }

            $totalRefund -= $amountPayPerRefund;
            $totalToPay -= $amountPayPerRefund;
        }

        $paymentInInvoice = StorePayment::make()->action($invoice->customer, $paymentAccount, [
            'amount' => abs(Arr::get($modelData, 'amount')) * -1,
            'status' => PaymentStatusEnum::SUCCESS->value,
            'state' => PaymentStateEnum::COMPLETED->value,
        ]);

        // invoice
        AttachPaymentToInvoice::make()->action($invoice, $paymentInInvoice, []);

        if ($type === 'credit') {
            CustomerHydrateCreditTransactions::dispatch($invoice->customer);
        }

        return $invoice;

    }

    public function rules(): array
    {
        return [
            'amount'    => ['required', 'numeric'],
            'type'     => ['required', 'string', 'in:payment,credit'],
        ];
    }

    public function action(Invoice $invoice, PaymentAccount $paymentAccount, array $modelData): Invoice
    {
        $this->asAction = true;
        $this->initialisationFromShop($invoice->shop, $modelData);
        return $this->handle($invoice, $paymentAccount, $modelData);
    }
}
