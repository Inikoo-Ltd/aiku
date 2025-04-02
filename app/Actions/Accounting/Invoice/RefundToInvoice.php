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
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\PaymentAccount;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundToInvoice extends OrgAction
{
    public function handle(Invoice $invoice, PaymentAccount $paymentAccount, array $modelData): Invoice
    {

        $type = Arr::get($modelData, 'type_refund', 'payment');
        $totalRefund = -abs(Arr::get($modelData, 'amount'));


        $refundsQuery = $invoice->refunds->where('in_process', false)->where('pay_status', InvoicePayStatusEnum::UNPAID);

        $refunds = $refundsQuery->sortByDesc('total_amount')->all();

        $totalNeedToRefund = $refundsQuery->sum('total_amount') - $refundsQuery->sum('payment_amount');



        $totalRoundRefund = abs(round($totalRefund, 2));

        if ($invoice->payment_amount > 0) {
            $paymentAmountWithInCertainType = DB::table('invoices')
                ->where('invoices.id', $invoice->id)
                ->leftJoin('model_has_payments', 'model_has_payments.model_id', '=', 'invoices.id')
                ->where('model_has_payments.model_type', 'Invoice')
                ->leftJoin('payments', 'payments.id', '=', 'model_has_payments.payment_id')
                ->leftJoin('payment_accounts', 'payment_accounts.id', '=', 'payments.payment_account_id')
                ->when(
                    $type === 'credit',
                    function ($query) {
                        $query->where('payment_accounts.type', PaymentAccountTypeEnum::ACCOUNT->value);
                    },
                    function ($query) {
                        $query->whereNot('payment_accounts.type', PaymentAccountTypeEnum::ACCOUNT->value);
                    }
                )
                ->sum('payments.amount');

            if ($totalRoundRefund > abs($paymentAmountWithInCertainType)) {
                throw ValidationException::withMessages(
                    [
                        'message' => [
                            'amount' => 'The refund amount exceeds the total paid amount in ' . ($type == 'credit' ? 'credit balance' : 'payment method'),
                        ]
                    ]
                );
            }
        }

        if ($totalRoundRefund > abs(round($totalNeedToRefund, 2))) {
            throw ValidationException::withMessages(
                [
                    'message' => [
                        'amount' => 'The refund amount exceeds the total amount that needs to be refunded',
                    ]
                ]
            );
        }

        foreach ($refunds as $refund) {
            if ($totalNeedToRefund >= 0 || $totalRefund >= 0) {
                break;
            }

            $amountPayPerRefund = max($totalRefund, $refund->total_amount);

            $paymentInRefund = StorePayment::make()->action($invoice->customer, $paymentAccount, [
                'amount' => $amountPayPerRefund,
                'status' => PaymentStatusEnum::SUCCESS->value,
                'state' => PaymentStateEnum::COMPLETED->value,
                'type' => PaymentTypeEnum::REFUND,
                'original_payment_id' => Arr::get($modelData, 'original_payment_id'),
            ]);

            // for invoice refund
            AttachPaymentToInvoice::make()->action($refund, $paymentInRefund, []);

            AttachPaymentToInvoice::make()->action($invoice, $paymentInRefund, []);

            // if only update the refund invoice and no need payment data for refund invoice
            // $payStatus             = InvoicePayStatusEnum::UNPAID;
            // $paymentAt             = null;
            // $runningPaymentsAmount = 0;

            // if ($payStatus == InvoicePayStatusEnum::UNPAID && abs($amountPayPerRefund) >= abs($invoice->total_amount)) {
            //     $payStatus = InvoicePayStatusEnum::PAID;
            //     $paymentAt = now();
            // }

            // $invoice->update(
            //     [
            //         'pay_status'     => $payStatus,
            //         'paid_at'        => $paymentAt,
            //         'payment_amount' => $runningPaymentsAmount
            //     ]
            // );

            if ($type === 'credit') {
                StoreCreditTransaction::make()->action($invoice->customer, [
                    'amount' => abs($amountPayPerRefund),
                    'date'  => now(),
                    'type' => CreditTransactionTypeEnum::MONEY_BACK
                ]);
            }

            $totalNeedToRefund -= $amountPayPerRefund;
            $totalRefund -= $amountPayPerRefund;
        }

        // $paymentInInvoice = StorePayment::make()->action($invoice->customer, $paymentAccount, [
        //     'amount' => abs(Arr::get($modelData, 'amount')) * -1,
        //     'status' => PaymentStatusEnum::SUCCESS->value,
        //     'state' => PaymentStateEnum::COMPLETED->value,
        //     'type' => PaymentTypeEnum::REFUND,
        //     'original_payment_id' => Arr::get($modelData, 'original_payment_id'),
        // ]);

        // invoice
        // AttachPaymentToInvoice::make()->action($invoice, $paymentInInvoice, []);

        if ($type === 'credit') {
            if ($this->asAction) {
                CustomerHydrateCreditTransactions::run($invoice->customer);
            } else {
                CustomerHydrateCreditTransactions::dispatch($invoice->customer);
            }
        }

        return $invoice;

    }

    public function rules(): array
    {
        return [
            'amount'    => ['required', 'numeric'],
            'type_refund'     => ['required', 'string', 'in:payment,credit'],
            'original_payment_id' => ['sometimes', 'nullable', 'exists:payments,id'],
        ];
    }

    public function action(Invoice $invoice, PaymentAccount $paymentAccount, array $modelData): Invoice
    {
        $this->asAction = true;
        $this->initialisationFromShop($invoice->shop, $modelData);
        return $this->handle($invoice, $paymentAccount, $modelData);
    }
}
