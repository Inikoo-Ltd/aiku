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
use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
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
use Illuminate\Validation\ValidationException;
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

            return $this->recordLine($bankConnection, $bankTransaction, $order, $paymentAccount, $matchData['received'], round($matchData['owed'] - $matchData['received'], 2), []);
        });

        if (!$paid instanceof Order) {
            return $paid ?? 'skipped';
        }

        $this->afterPayment($paid);

        return 'paid';
    }

    /**
     * A person chose the order for a bank line that was not paid automatically. The payment is the money received;
     * when $absorbBankFee and the transfer is short by no more than the matcher's fee tolerance, the shortfall is
     * absorbed as a bank fee; a bigger shortfall (a part payment) stays owed on the order.
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Throwable
     */
    public function payToOrder(BankTransaction $bankTransaction, Order $order, bool $absorbBankFee, string $paidBy): void
    {
        $paid = DB::transaction(function () use ($bankTransaction, $order, $absorbBankFee, $paidBy) {
            $bankTransaction = BankTransaction::lockForUpdate()->findOrFail($bankTransaction->id);
            $order           = Order::lockForUpdate()->findOrFail($order->id);
            $bankConnection  = $bankTransaction->bankConnection;

            $problem = match (true) {
                $bankTransaction->payment_id !== null => __('This bank payment is already recorded on an order'),
                MatchBankTransactions::make()->cardProcessorPayoutId($bankTransaction) !== null => __('This is a card processor payout of payments already on their orders'),
                $bankTransaction->direction !== BankTransactionDirectionEnum::IN || $bankTransaction->status !== BankTransactionStatusEnum::BOOKED => __('Only money received and booked by the bank can pay an order'),
                $order->organisation_id !== $bankTransaction->organisation_id => __('The order belongs to another organisation'),
                $order->currency_id !== $bankTransaction->currency_id => __('The order is in another currency'),
                $order->state === OrderStateEnum::CANCELLED => __('The order is cancelled'),
                $order->state === OrderStateEnum::CREATING => __('The order is still in the basket: wait until it is submitted'),
                $order->amountStillToPay() <= 0.01 => __('The order owes nothing'),
                default => null,
            };
            if (!$problem && $order->shop->isFaire()) {
                $this->acceptMarketplacePayout($bankTransaction, $order, $paidBy);

                return null;
            }

            $paymentAccount = $problem ? null : $this->paymentAccount($bankConnection, $bankTransaction);
            if (!$problem && !$paymentAccount) {
                $problem = __('No payment account is set for :currency on this bank connection', ['currency' => $bankTransaction->currency->code]);
            }
            if ($problem) {
                throw ValidationException::withMessages(['order' => $problem]);
            }

            $received = round((float)$bankTransaction->amount, 2);
            $short    = round($order->amountStillToPay() - $received, 2);
            $bankFee  = $absorbBankFee && $short > 0 && $short <= MatchBankTransactions::make()->feeTolerance($order->amountStillToPay(), $bankTransaction) ? $short : 0;

            $paid = $this->recordLine($bankConnection, $bankTransaction, $order, $paymentAccount, $received, $bankFee, [
                'paid_by'    => $paidBy,
                'reason'     => null,
                'owed'       => round($order->amountStillToPay(), 2),
                'received'   => $received,
                'difference' => round($received - $order->amountStillToPay(), 2),
            ]);
            if (!$paid instanceof Order) {
                throw ValidationException::withMessages(['order' => __('This organisation has no bank fee account yet: pay without absorbing the fee')]);
            }

            return $paid;
        });

        if ($paid) {
            $this->afterPayment($paid);
        }
    }

    /**
     * The retailer paid the marketplace, so its payout to us is not a customer payment: the line is linked to the
     * order, which reads as paid by the marketplace, whatever commission the marketplace kept.
     */
    private function acceptMarketplacePayout(BankTransaction $bankTransaction, Order $order, string $paidBy): void
    {
        $received = round((float)$bankTransaction->amount, 2);
        $owed     = round((float)$order->total_amount - (float)$order->commission_amount, 2);

        $bankTransaction->update([
            'match_state' => BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE,
            'order_id'    => $order->id,
            'matched_at'  => now(),
            'match_data'  => array_merge($bankTransaction->match_data, [
                'paid_by'    => $paidBy,
                'reason'     => null,
                'owed'       => $owed,
                'received'   => $received,
                'difference' => round($received - $owed, 2),
                'commission' => round((float)$order->commission_amount, 2),
                'paid_at'    => now()->toIso8601String(),
            ]),
        ]);

        UpdateOrderPaymentsStatus::run($order);
    }

    /**
     * @param array<string, mixed> $extraMatchData
     */
    private function recordLine(BankConnection $bankConnection, BankTransaction $bankTransaction, Order $order, PaymentAccount $paymentAccount, float $received, float $bankFee, array $extraMatchData): Order|string
    {
        $bankFeeAccount = PaymentAccount::where('organisation_id', $bankConnection->organisation_id)
            ->where('type', PaymentAccountTypeEnum::BANK_FEE)
            ->find($bankConnection->settings['bank_fee_payment_account_id'] ?? 0);
        if ($bankFee > 0 && !$bankFeeAccount) {
            return 'no bank fee account';
        }

        $payment = $this->recordPayment($order, $paymentAccount, $received, $bankTransaction->reference, $bankTransaction);

        $bankFeePayment = null;
        if ($bankFee > 0) {
            $bankFeePayment = $this->recordPayment($order, $bankFeeAccount, $bankFee, __('Bank fee absorbed on :reference', ['reference' => $order->reference]), $bankTransaction);
        }

        $bankTransaction->update([
            'match_state'         => BankTransactionMatchStateEnum::MATCHED,
            'order_id'            => $order->id,
            'payment_id'          => $payment->id,
            'bank_fee_payment_id' => $bankFeePayment?->id,
            'match_data'          => array_merge($bankTransaction->match_data, $extraMatchData, array_filter([
                'paid_at'           => now()->toIso8601String(),
                'bank_fee_absorbed' => max($bankFee, 0),
                'payer_sent'        => $bankTransaction->fee_amount !== null ? round((float)$bankTransaction->amount + (float)$bankTransaction->fee_amount, 2) : null,
            ], fn ($value) => $value !== null)),
        ]);

        return $order;
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
