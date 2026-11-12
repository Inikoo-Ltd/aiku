<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction;

use App\Actions\Accounting\Invoice\AttachPaymentToInvoice;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Comms\Outbox\ProcessInvoicePaidNotification;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\BankConnection;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\PaymentAccount;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Throwable;

/**
 * Records the payment for every matched bank line that arrived after auto-pay was switched on for its connection.
 * The bank payment is always the money that arrived, on the payment account mapped to the line's currency; when the
 * transfer came in short by a bank fee within the matcher's tolerance, the difference is absorbed as a second payment
 * on the organisation's bank fee account, so the order is paid and the customer is never asked for it.
 */
class PayMatchedBankTransactions
{
    use AsAction;

    /**
     * @return array<string, int>
     */
    public function handle(BankConnection $bankConnection): array
    {
        $autoPayFrom = $bankConnection->settings['auto_pay_from'] ?? null;
        if (!$autoPayFrom) {
            return [];
        }

        $counts = [];
        $count  = function (string $outcome) use (&$counts) {
            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
        };

        $bankTransactionIds = BankTransaction::where('bank_connection_id', $bankConnection->id)
            ->where('match_state', BankTransactionMatchStateEnum::MATCHED)
            ->where('status', BankTransactionStatusEnum::BOOKED)
            ->whereNull('payment_id')
            ->where('booked_at', '>=', $autoPayFrom)
            ->orderBy('id')
            ->pluck('id');

        foreach ($bankTransactionIds as $bankTransactionId) {
            try {
                $count($this->pay($bankConnection, $bankTransactionId));
            } catch (Throwable $exception) {
                Sentry::captureException($exception);
                $count('failed');
            }
        }

        return $counts;
    }

    private function pay(BankConnection $bankConnection, int $bankTransactionId): string
    {
        $paid = DB::transaction(function () use ($bankConnection, $bankTransactionId) {
            $bankTransaction = BankTransaction::lockForUpdate()->findOrFail($bankTransactionId);
            if ($bankTransaction->payment_id || $bankTransaction->match_state !== BankTransactionMatchStateEnum::MATCHED) {
                return null;
            }

            $paymentAccount = $this->paymentAccount($bankConnection, $bankTransaction);
            if (!$paymentAccount) {
                return 'no payment account for this currency';
            }

            Order::lockForUpdate()->findOrFail($bankTransaction->order_id);

            [$state, $order, $matchData] = MatchBankTransactions::make()->decide($bankTransaction);
            if ($state !== BankTransactionMatchStateEnum::MATCHED || $order?->id !== $bankTransaction->order_id) {
                $bankTransaction->update([
                    'match_state' => $state,
                    'order_id'    => $order?->id,
                    'match_data'  => array_merge($matchData, ['decided_again_at_pay_time' => true]),
                    'matched_at'  => now(),
                ]);

                return 'decided again: '.$state->value;
            }

            $bankFee = round($matchData['owed'] - $matchData['received'], 2);

            $bankFeeAccount = PaymentAccount::where('organisation_id', $bankConnection->organisation_id)
                ->where('type', PaymentAccountTypeEnum::BANK_FEE)
                ->find($bankConnection->settings['bank_fee_payment_account_id'] ?? 0);
            if ($bankFee > 0 && !$bankFeeAccount) {
                return 'no bank fee account';
            }

            $payment = $this->recordPayment($order, $paymentAccount, $matchData['received'], $bankTransaction->reference, $bankTransaction);

            $bankFeePayment = null;
            if ($bankFee > 0) {
                $bankFeePayment = $this->recordPayment($order, $bankFeeAccount, $bankFee, __('Bank fee absorbed on :reference', ['reference' => $order->reference]), $bankTransaction);
            }

            $bankTransaction->update([
                'payment_id'          => $payment->id,
                'bank_fee_payment_id' => $bankFeePayment?->id,
                'match_data'          => array_merge($bankTransaction->match_data, array_filter([
                    'paid_at'           => now()->toIso8601String(),
                    'bank_fee_absorbed' => max($bankFee, 0),
                    'payer_sent'        => $bankTransaction->fee_amount !== null ? round((float)$bankTransaction->amount + (float)$bankTransaction->fee_amount, 2) : null,
                ], fn ($value) => $value !== null)),
            ]);

            return $order;
        });

        if (!$paid instanceof Order) {
            return $paid ?? 'skipped';
        }

        $this->afterPayment($paid);

        return 'paid';
    }

    private function paymentAccount(BankConnection $bankConnection, BankTransaction $bankTransaction): ?PaymentAccount
    {
        $paymentAccountId = $bankConnection->settings['payment_accounts'][$bankTransaction->currency->code] ?? null;

        return $paymentAccountId
            ? PaymentAccount::where('organisation_id', $bankConnection->organisation_id)->find($paymentAccountId)
            : null;
    }

    private function recordPayment(Order $order, PaymentAccount $paymentAccount, float $amount, ?string $reference, BankTransaction $bankTransaction): \App\Models\Accounting\Payment
    {
        $payment = StorePayment::make()->action($order->customer, $paymentAccount, [
            'amount'    => round($amount, 2),
            'reference' => $reference ? mb_substr($reference, 0, 255) : null,
            'status'    => PaymentStatusEnum::SUCCESS,
            'state'     => PaymentStateEnum::COMPLETED,
            'data'      => ['bank_transaction_id' => $bankTransaction->id],
        ]);

        AttachPaymentToOrder::make()->action($order, $payment, []);

        $invoice = $order->invoices()->where('invoices.type', InvoiceTypeEnum::INVOICE)->first();
        if ($invoice) {
            AttachPaymentToInvoice::make()->action($invoice, $payment, []);
        }

        return $payment;
    }

    private function afterPayment(Order $order): void
    {
        $order->refresh();

        $invoice = $order->invoices()->where('invoices.type', InvoiceTypeEnum::INVOICE)->first();
        if ($invoice) {
            ProcessInvoicePaidNotification::dispatch($invoice->id);
        }

        if ($order->state !== OrderStateEnum::SUBMITTED || $order->pay_status !== OrderPayStatusEnum::PAID) {
            return;
        }

        try {
            SendOrderToWarehouse::make()->action($order, []);
        } catch (Throwable $exception) {
            Sentry::captureException($exception);
        }
    }
}
