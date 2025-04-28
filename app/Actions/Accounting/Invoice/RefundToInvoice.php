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
    private Invoice $invoice;
    private PaymentAccount $paymentAccount;

    /**
     * @throws \Throwable
     */
    public function handle(Invoice $invoice, PaymentAccount $paymentAccount, array $modelData): Invoice
    {
        $type        = Arr::get($modelData, 'type_refund', 'payment');
        $totalRefund = -abs(Arr::get($modelData, 'amount'));


        $refundsQuery = $invoice->refunds->where('in_process', false)->where('pay_status', InvoicePayStatusEnum::UNPAID);

        $refunds = $refundsQuery->sortByDesc('total_amount')->all();

        $totalNeedToRefund = $refundsQuery->sum('total_amount') - $refundsQuery->sum('payment_amount');

        foreach ($refunds as $refund) {
            if ($totalNeedToRefund >= 0 || $totalRefund >= 0) {
                break;
            }

            $amountPayPerRefund = max($totalRefund, $refund->total_amount);

            $paymentInRefund = StorePayment::make()->action($invoice->customer, $paymentAccount, [
                'amount'              => $amountPayPerRefund,
                'status'              => PaymentStatusEnum::SUCCESS->value,
                'state'               => PaymentStateEnum::COMPLETED->value,
                'type'                => PaymentTypeEnum::REFUND,
                'original_payment_id' => Arr::get($modelData, 'original_payment_id'),
            ]);

            // for invoice refund
            AttachPaymentToInvoice::make()->action($refund, $paymentInRefund, []);

            AttachPaymentToInvoice::make()->action($invoice, $paymentInRefund, []);


            if ($type === 'credit') {
                StoreCreditTransaction::make()->action($invoice->customer, [
                    'amount' => abs($amountPayPerRefund),
                    'date'   => now(),
                    'type'   => CreditTransactionTypeEnum::MONEY_BACK
                ]);
            }

            $totalNeedToRefund -= $amountPayPerRefund;
            $totalRefund       -= $amountPayPerRefund;
        }


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
            'amount'              => ['required', 'numeric'],
            'type_refund'         => ['required', 'string', 'in:payment,credit'],
            'original_payment_id' => ['sometimes', 'nullable', 'exists:payments,id'],
            'is_auto_refund'      => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function afterValidator(): void
    {
        $type = $this->get("type_refund") ?? 'payment';
        $totalRefund = -abs($this->get("amount"));
        $totalRoundRefund = abs(round($totalRefund, 2));

        $refundsQuery = $this->invoice->refunds->where('in_process', false)->where('pay_status', InvoicePayStatusEnum::UNPAID);
        $totalNeedToRefund = $refundsQuery->sum('total_amount') - $refundsQuery->sum('payment_amount');

        if ($this->paymentAccount->type != PaymentAccountTypeEnum::ACCOUNT && $this->invoice->payment_amount > 0 && !$this->get("is_auto_refund", false)) {
            $paymentAmountWithInCertainType = DB::table('invoices')
                ->where('invoices.id', $this->invoice->id)
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
    }

    /**
     * @throws \Throwable
     */
    public function action(Invoice $invoice, PaymentAccount $paymentAccount, array $modelData): Invoice
    {
        $this->asAction = true;
        $this->invoice = $invoice;
        $this->paymentAccount = $paymentAccount;
        $this->initialisationFromShop($invoice->shop, $modelData);

        return $this->handle($invoice, $paymentAccount, $modelData);
    }
}
