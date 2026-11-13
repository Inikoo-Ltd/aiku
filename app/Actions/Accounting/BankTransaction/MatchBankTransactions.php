<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction;

use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\BankConnection;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Models\Accounting\BankTransaction;
use App\Models\Helpers\Currency;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Throwable;

/**
 * Decides, for each booked incoming customer transfer not yet looked at, which order it pays. Nothing is paid here.
 * matched means safe to pay automatically: one order named in the reference, same currency, order submitted or later
 * and not cancelled, no payment on it yet, no other bank line already matched to it, and the amount covers what is
 * still owed within a bank-fee tolerance. A payment step must still decide again at pay time, under a lock, because
 * the order can change after this label is written.
 */
class MatchBankTransactions
{
    use AsAction;

    /** Bank line types that are money sent to us by someone else. */
    public const array CUSTOMER_TRANSFER_TYPES = ['TRANSFER'];

    public string $commandSignature = 'bank-transactions:match {bankConnection : id} {--reset : forget earlier decisions on lines that paid nothing}';

    /**
     * @return array<string, int> lines per match state
     */
    public function handle(BankConnection $bankConnection): array
    {
        $counts = [];

        BankTransaction::where('bank_connection_id', $bankConnection->id)
            ->where('direction', BankTransactionDirectionEnum::IN)
            ->where('status', BankTransactionStatusEnum::BOOKED)
            ->whereIn('provider_type', self::CUSTOMER_TRANSFER_TYPES)
            ->whereNull('match_state')
            ->chunkById(500, function ($bankTransactions) use (&$counts) {
                foreach ($bankTransactions as $bankTransaction) {
                    try {
                        [$state, $order, $matchData] = $this->decide($bankTransaction);
                    } catch (Throwable $exception) {
                        Sentry::captureException($exception);
                        [$state, $order, $matchData] = [BankTransactionMatchStateEnum::REVIEW, null, ['reason' => 'error while matching: '.mb_substr($exception->getMessage(), 0, 200)]];
                    }

                    $bankTransaction->update([
                        'match_state' => $state,
                        'order_id'    => $order?->id,
                        'match_data'  => $matchData,
                        'matched_at'  => now(),
                    ]);
                    $counts[$state->value] = ($counts[$state->value] ?? 0) + 1;
                }
            });

        BankTransaction::where('bank_connection_id', $bankConnection->id)
            ->where('match_state', BankTransactionMatchStateEnum::REVIEW)
            ->whereNull('payment_id')
            ->chunkById(500, function ($bankTransactions) use (&$counts) {
                foreach ($bankTransactions as $bankTransaction) {
                    if ($this->paidByHandSinceReview($bankTransaction)) {
                        $counts[BankTransactionMatchStateEnum::ALREADY_PAID->value] = ($counts[BankTransactionMatchStateEnum::ALREADY_PAID->value] ?? 0) + 1;
                    }
                }
            });

        return $counts;
    }

    /**
     * A line waiting in review whose order staff have since paid by hand leaves the queue. A review line only ever
     * becomes already paid here, never matched: a person may have pulled it back from auto-pay on purpose.
     */
    private function paidByHandSinceReview(BankTransaction $bankTransaction): bool
    {
        try {
            [$state, $order, $matchData] = $this->decide($bankTransaction);
        } catch (Throwable $exception) {
            Sentry::captureException($exception);

            return false;
        }

        if ($state !== BankTransactionMatchStateEnum::ALREADY_PAID) {
            return false;
        }

        $bankTransaction->update([
            'match_state' => $state,
            'order_id'    => $order?->id ?? $bankTransaction->order_id,
            'match_data'  => $matchData,
            'matched_at'  => now(),
        ]);

        return true;
    }

    /**
     * @return array{0: BankTransactionMatchStateEnum, 1: Order|null, 2: array<string, mixed>}
     */
    public function decide(BankTransaction $bankTransaction): array
    {
        $tokens = $this->orderReferenceTokens($bankTransaction->reference);
        if (!$tokens) {
            return [BankTransactionMatchStateEnum::UNMATCHED, null, ['reason' => 'no order number in the reference']];
        }

        $orders = Order::where('organisation_id', $bankTransaction->organisation_id)->whereIn('reference', $tokens)->get();
        if ($orders->isEmpty()) {
            return [BankTransactionMatchStateEnum::UNMATCHED, null, ['reason' => 'order number not found', 'tokens' => $tokens]];
        }
        if ($orders->count() > 1) {
            $orderIds = $orders->pluck('id')->all();

            return $orders->every(fn (Order $order) => $this->settledWithPayment($order))
                ? [BankTransactionMatchStateEnum::ALREADY_PAID, null, ['reason' => 'every order named is already paid', 'order_ids' => $orderIds]]
                : [BankTransactionMatchStateEnum::REVIEW, null, ['reason' => 'several orders named', 'order_ids' => $orderIds]];
        }

        $order = $orders->first();

        $releasedFromThisOrder = $order->payments()
            ->where('payments.state', PaymentStateEnum::CANCELLED)
            ->where('payments.data->bank_transaction_id', $bankTransaction->id)
            ->exists();
        if ($releasedFromThisOrder) {
            return [BankTransactionMatchStateEnum::REVIEW, $order, ['reason' => 'a payment from this line on this order was cancelled']];
        }

        if ($order->currency_id !== $bankTransaction->currency_id) {
            return $this->settledWithPayment($order)
                ? [BankTransactionMatchStateEnum::ALREADY_PAID, $order, ['reason' => 'order already settled']]
                : [BankTransactionMatchStateEnum::REVIEW, $order, ['reason' => 'currency differs from the order']];
        }

        $received            = round((float)$bankTransaction->amount, 2);
        $successfulPayments  = $this->successfulPayments($order);
        $sameAmountPaymentId = (clone $successfulPayments)->whereBetween('payments.amount', [$received - 0.01, $received + 0.01])->value('payments.id');
        $secondTransfer      = $sameAmountPaymentId && BankTransaction::where('payment_id', $sameAmountPaymentId)->where('id', '!=', $bankTransaction->id)->exists();

        if ($order->state === OrderStateEnum::CANCELLED) {
            return $sameAmountPaymentId && !$secondTransfer
                ? [BankTransactionMatchStateEnum::ALREADY_PAID, $order, ['reason' => 'a payment of this amount is already on the order']]
                : [BankTransactionMatchStateEnum::REVIEW, $order, ['reason' => 'order cancelled']];
        }
        if ($order->state === OrderStateEnum::CREATING) {
            return [BankTransactionMatchStateEnum::REVIEW, $order, ['reason' => 'order still in the basket']];
        }

        $owed       = $order->amountStillToPay();
        $difference = round($received - $owed, 2);
        $matchData  = ['owed' => $owed, 'received' => $received, 'difference' => $difference];

        if ($secondTransfer) {
            return [BankTransactionMatchStateEnum::REVIEW, $order, array_merge($matchData, ['reason' => 'a second transfer of the same amount, the first one already paid this order'])];
        }
        if ($sameAmountPaymentId) {
            return [BankTransactionMatchStateEnum::ALREADY_PAID, $order, array_merge($matchData, ['reason' => 'a payment of this amount is already on the order'])];
        }
        $hasSuccessfulPayment = (clone $successfulPayments)->exists();
        if ($owed <= 0.01 && $order->absorbedBankFees() > 0) {
            return [BankTransactionMatchStateEnum::REVIEW, $order, array_merge($matchData, ['reason' => 'a bank fee was absorbed on this order, the customer may be paying it'])];
        }
        if ($owed <= 0.01) {
            return $hasSuccessfulPayment
                ? [BankTransactionMatchStateEnum::ALREADY_PAID, $order, array_merge($matchData, ['reason' => 'order already settled'])]
                : [BankTransactionMatchStateEnum::REVIEW, $order, array_merge($matchData, ['reason' => 'order owes nothing but has no payment'])];
        }
        if ($hasSuccessfulPayment) {
            return [BankTransactionMatchStateEnum::REVIEW, $order, array_merge($matchData, ['reason' => 'order already has a payment'])];
        }

        $alreadyMatched = BankTransaction::where('order_id', $order->id)
            ->where('id', '!=', $bankTransaction->id)
            ->where('match_state', BankTransactionMatchStateEnum::MATCHED)
            ->exists();
        if ($alreadyMatched) {
            return [BankTransactionMatchStateEnum::REVIEW, $order, array_merge($matchData, ['reason' => 'another bank line is already matched to this order'])];
        }

        if ($difference > $this->overpayTolerance($owed, $bankTransaction)) {
            return [BankTransactionMatchStateEnum::REVIEW, $order, array_merge($matchData, ['reason' => 'pays more than owed'])];
        }
        if (-$difference > $this->feeTolerance($owed, $bankTransaction)) {
            return [BankTransactionMatchStateEnum::REVIEW, $order, array_merge($matchData, ['reason' => 'pays less than owed'])];
        }

        return [BankTransactionMatchStateEnum::MATCHED, $order, $matchData];
    }

    private function successfulPayments(Order $order): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $order->payments()
            ->where('payments.status', PaymentStatusEnum::SUCCESS)
            ->whereNot('payments.state', PaymentStateEnum::CANCELLED)
            ->whereHas('paymentAccount', fn ($query) => $query->whereNot('type', PaymentAccountTypeEnum::BANK_FEE));
    }

    private function settledWithPayment(Order $order): bool
    {
        return $order->state !== OrderStateEnum::CANCELLED
            && $order->amountStillToPay() <= 0.01
            && $order->absorbedBankFees() <= 0
            && $this->successfulPayments($order)->exists();
    }

    public const float DEFAULT_FEE_CEILING_EUR = 10;

    public const float OVERPAY_CEILING_EUR = 5;

    /** @var array<int, float> */
    private array $euroRates = [];

    /**
     * Banks take small fees off incoming transfers (0.2-0.8% seen on Wise): accept up to 1% short, never more than the
     * connection's ceiling (10 EUR by default, the largest fee seen was 9.95) in the line's currency. A line short by more goes to review.
     */
    public function feeTolerance(float $owed, BankTransaction $bankTransaction): float
    {
        $ceilingEur = (float)($bankTransaction->bankConnection->settings['fee_ceiling_eur'] ?? self::DEFAULT_FEE_CEILING_EUR);

        return round(min($owed * 0.01, $ceilingEur * $this->euroRate($bankTransaction)), 2);
    }

    /**
     * A transfer rounded up (whole forints) pays the order on its own when it is over by at most 0.5%, never more
     * than 5 EUR in the line's currency; the extra stays on the order. A bigger overpayment goes to review.
     */
    public function overpayTolerance(float $owed, BankTransaction $bankTransaction): float
    {
        return round(min($owed * 0.005, self::OVERPAY_CEILING_EUR * $this->euroRate($bankTransaction)), 2);
    }

    private function euroRate(BankTransaction $bankTransaction): float
    {
        return $this->euroRates[$bankTransaction->currency_id] ??= (function () use ($bankTransaction) {
            $euro = Currency::where('code', 'EUR')->firstOrFail();

            return (float)($bankTransaction->currency_id === $euro->id ? 1.0 : GetCurrencyExchange::run($euro, $bankTransaction->currency));
        })();
    }

    /**
     * @return list<string> upper-cased order-number-shaped words, e.g. PL31515 or DEU123456
     */
    public function orderReferenceTokens(?string $reference): array
    {
        preg_match_all('/(?<![A-Za-z0-9])([A-Za-z]{2,5}[0-9]{3,8})(?![A-Za-z0-9])/', (string)$reference, $matches);

        return array_values(array_unique(array_map('strtoupper', $matches[1])));
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $bankConnection = BankConnection::findOrFail($command->argument('bankConnection'));

        $lock = Cache::lock('bank-connection-sync:'.$bankConnection->id, 6 * 3600);
        if (!$lock->get()) {
            $command->error('This bank connection is syncing, try again later');

            return 1;
        }

        try {
            if ($command->option('reset')) {
                BankTransaction::where('bank_connection_id', $bankConnection->id)
                    ->whereNull('payment_id')
                    ->where(fn ($query) => $query->whereNull('match_state')->orWhere('match_state', '!=', BankTransactionMatchStateEnum::DISMISSED))
                    ->update(['match_state' => null, 'order_id' => null, 'match_data' => '{}', 'matched_at' => null]);
            }

            foreach ($this->handle($bankConnection) as $state => $lines) {
                $command->info("$state: $lines");
            }
        } finally {
            $lock->release();
        }

        return 0;
    }
}
