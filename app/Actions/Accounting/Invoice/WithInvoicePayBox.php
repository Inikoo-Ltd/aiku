<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\Invoice;

use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Http\Resources\Accounting\RefundResource;
use App\Models\Accounting\Invoice;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait WithInvoicePayBox
{
    public function getPayBoxData(?Invoice $invoice): array
    {
        if (!$invoice) {
            return [];
        }

        $totalRefund =  $invoice->refunds->where('in_process', false)->sum('total_amount');
        $irTotal    = $invoice->total_amount + $totalRefund;
        $refundsPayOut = $invoice->refunds->where('in_process', false)->sum('payment_amount');

        $totalPaidAccount = $this->getTotalPaidAccount($invoice);
        $totalPaidRefundInOtherPayment = $this->getTotalPaidRefundInOtherPayment($invoice);


        $totalNeedToRefund = (abs($totalRefund) - abs($refundsPayOut)) * -1;


        $totalExcessPayment = ($invoice->payment_amount - $invoice->total_amount) > 0 ? $invoice->payment_amount - $invoice->total_amount : 0;

        $consolidateTotalPayments = Arr::get($invoice->shop->settings, 'consolidate_invoice_to_pay', true);

        if ($consolidateTotalPayments) {
            $totalPaidIn = $invoice->payment_amount;
            $totalNeedToPay = round($invoice->total_amount - abs($totalNeedToRefund), 2) - $invoice->payment_amount;
        } else {
            $totalPaidIn = $invoice->payment_amount + abs($refundsPayOut);
            $totalNeedToPay = round($invoice->total_amount - $totalPaidIn, 2);
        }

        $totalNeedToRefundInPaymentMethod = 0;
        $totalNeedToRefundInCreditMethod = 0;

        if ($totalNeedToPay <= 0) {
            if ($totalNeedToRefund < 0) {
                $totalNeedToPay = $totalNeedToRefund;

                if (abs($totalNeedToPay) > $invoice->payment_amount) {
                    $totalNeedToPay = $invoice->payment_amount * -1;
                }

                $paidAllPayment = $invoice->payment_amount + abs($refundsPayOut);

                // payment method
                if (abs($paidAllPayment - $totalPaidAccount) > 0) {
                    if (abs($totalNeedToRefund) > abs($totalPaidRefundInOtherPayment)) {
                        $totalPaymentAfterRefunded = ($paidAllPayment - $totalPaidAccount) - abs($totalPaidRefundInOtherPayment);
                        $totalNeedToRefundInPaymentMethod = min($totalPaymentAfterRefunded, abs($totalNeedToPay)) * -1;

                    } else {
                        $totalNeedToRefundInPaymentMethod = $totalNeedToRefund;
                    }
                }

                // credit method
                if ($totalPaidAccount > 0) {
                    if (abs($totalNeedToRefund) > $totalPaidAccount) {
                        $totalNeedToRefundInCreditMethod = $totalPaidAccount * -1;
                    } else {
                        $totalNeedToRefundInCreditMethod = $totalNeedToRefund;
                    }
                }

            } else {
                $totalNeedToPay = 0;
            }
        }

        return [
            'invoice_pay' => [
                'invoice_slug'  => $invoice->slug,
                'invoice_id'     => $invoice->id,
                'invoice_reference'     => $invoice->reference,
                'routes'         => [
                    'fetch_payment_accounts' => [
                        'name'       => 'grp.json.shop.payment-accounts',
                        'parameters' => [
                            'shop' => $invoice->shop->slug
                        ]
                    ],
                    'submit_payment'         => [
                        'name'       => 'grp.models.invoice.payment.store',
                        'parameters' => [
                            'invoice'  => $invoice->id,
                        ]
                    ],
                    'payments'  => [
                        'name'       => 'grp.json.refund.show.payments.index',
                        'parameters' => [
                            'invoice'  => $invoice->id,
                        ]
                    ],
                ],
                'list_refunds'      => RefundResource::collection($invoice->refunds->where('in_process', false)),
                'currency_code'     => $invoice->currency->code,
                'total_invoice'     => $invoice->total_amount,
                'total_refunds'     => $totalRefund,
                'total_balance'     => $irTotal,
                'total_paid_in'     => $totalPaidIn,
                'total_paid_out'    => $refundsPayOut,
                'total_excess_payment' => $totalExcessPayment,
                'total_need_to_refund_in_payment_method' => $totalNeedToRefundInPaymentMethod,
                'total_need_to_refund_in_credit_method' => $totalNeedToRefundInCreditMethod,
                'total_paid_account' => $totalPaidAccount,
                'total_need_to_pay' => $totalNeedToPay,
            ],
        ];
    }

    protected function getTotalPaidAccount(Invoice $invoice)
    {
        return DB::table('invoices')
            ->where('invoices.id', $invoice->id)
            ->leftJoin('model_has_payments', 'model_has_payments.model_id', '=', 'invoices.id')
            ->where('model_has_payments.model_type', 'Invoice')
            ->leftJoin('payments', 'payments.id', '=', 'model_has_payments.payment_id')
            ->leftJoin('payment_accounts', 'payment_accounts.id', '=', 'payments.payment_account_id')
            ->where('payment_accounts.type', PaymentAccountTypeEnum::ACCOUNT->value)->sum('payments.amount');

    }

    protected function getTotalPaidRefundInOtherPayment(Invoice $invoice)
    {
        return DB::table('invoices')
            ->where('invoices.id', $invoice->id)
            ->leftJoin('model_has_payments', 'model_has_payments.model_id', '=', 'invoices.id')
            ->where('model_has_payments.model_type', 'Invoice')
            ->leftJoin('payments', 'payments.id', '=', 'model_has_payments.payment_id')
            ->leftJoin('payments as payments_refund', 'payments_refund.original_payment_id', '=', 'model_has_payments.payment_id')
            ->leftJoin('payment_accounts', 'payment_accounts.id', '=', 'payments_refund.payment_account_id')
            ->whereNot('payment_accounts.type', PaymentAccountTypeEnum::ACCOUNT->value)->sum('payments_refund.amount');

    }
}
