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
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\BankConnection;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\Helpers\Currency;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    public string $commandSignature = 'bank-transactions:match {bankConnection : id} {--reset : forget earlier decisions on lines that paid nothing} {--unmatched : decide again only the lines left unmatched}';

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
                    if ($state === BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE) {
                        $this->refreshMarketplaceOrders([$order->id]);
                    }
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
     * A line waiting in review leaves the queue only when staff have since recorded, by hand, bank payments of this
     * amount on the orders it names. An order settled by other money (a card, the customer's balance) leaves the line
     * where it is. A review line only ever becomes already paid here, never matched: a person may have pulled it back
     * from auto-pay on purpose.
     */
    private function paidByHandSinceReview(BankTransaction $bankTransaction): bool
    {
        try {
            return DB::transaction(function () use ($bankTransaction) {
                $bankTransaction = BankTransaction::lockForUpdate()->find($bankTransaction->id);
                if (!$bankTransaction || $bankTransaction->payment_id || $bankTransaction->match_state !== BankTransactionMatchStateEnum::REVIEW) {
                    return false;
                }

                [$state, $order, $matchData] = $this->decide($bankTransaction);
                if ($state !== BankTransactionMatchStateEnum::ALREADY_PAID) {
                    return false;
                }

                $orders = $order ? collect([$order]) : Order::whereIn('id', $matchData['order_ids'] ?? [])->get();
                if (!isset($matchData['invoice_ids']) && !$this->explainedByHandBankPayments($orders, $bankTransaction)) {
                    return false;
                }

                $bankTransaction->update([
                    'match_state' => $state,
                    'order_id'    => $order?->id ?? $bankTransaction->order_id,
                    'match_data'  => $matchData,
                    'matched_at'  => now(),
                ]);

                return true;
            });
        } catch (Throwable $exception) {
            Sentry::captureException($exception);

            return false;
        }
    }

    /**
     * Bank payments recorded on these orders (or invoices) that no other bank line already accounts for add up to what
     * this line received, within the bank fee tolerance.
     */
    private function explainedByHandBankPayments(Collection $orders, BankTransaction $bankTransaction, string $modelType = 'Order', ?\DateTimeInterface $recordedSince = null): bool
    {
        if ($orders->isEmpty()) {
            return false;
        }

        $received = round((float)$bankTransaction->amount, 2);
        $recorded = (float)Payment::query()
            ->whereIn('payments.id', DB::table('model_has_payments')->where('model_type', $modelType)->whereIn('model_id', $orders->pluck('id'))->select('payment_id'))
            ->where('payments.currency_id', $bankTransaction->currency_id)
            ->where('payments.status', PaymentStatusEnum::SUCCESS)
            ->whereNot('payments.state', PaymentStateEnum::CANCELLED)
            ->whereHas('paymentAccount', fn ($query) => $query->where('type', PaymentAccountTypeEnum::BANK))
            ->whereNotIn('payments.id', BankTransaction::whereNotNull('payment_id')->where('id', '!=', $bankTransaction->id)->select('payment_id'))
            ->when($recordedSince, fn ($query) => $query->where('payments.date', '>=', $recordedSince))
            ->sum('payments.amount');

        return $recorded > 0 && abs(round($recorded - $received, 2)) <= max(0.01, $this->feeTolerance($received, $bankTransaction));
    }

    /**
     * @return array{0: BankTransactionMatchStateEnum, 1: Order|null, 2: array<string, mixed>}
     */
    public function decide(BankTransaction $bankTransaction): array
    {
        $payoutId = $this->cardProcessorPayoutId($bankTransaction);
        if ($payoutId) {
            return [BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT, null, ['provider' => 'checkout', 'payout_id' => $payoutId]];
        }

        if (preg_match('/^pastpay\b/i', (string)$bankTransaction->counterparty_name)) {
            return $this->decidePastpayPayout($bankTransaction);
        }

        $marketplaceOrderReference = $this->marketplaceOrderReference($bankTransaction);
        if ($marketplaceOrderReference) {
            return $this->decideMarketplacePayout($bankTransaction, $marketplaceOrderReference);
        }

        $tokens = $this->orderReferenceTokens($bankTransaction->reference);
        if (!$tokens) {
            return $this->decideInvoice($bankTransaction, []);
        }

        $orders = Order::where('organisation_id', $bankTransaction->organisation_id)->whereIn('reference', $tokens)->get();
        if ($orders->isEmpty()) {
            return $this->decideInvoice($bankTransaction, $tokens);
        }
        $releasedFromThisLine = Payment::query()
            ->whereIn('payments.id', DB::table('model_has_payments')->where('model_type', 'Order')->whereIn('model_id', $orders->pluck('id'))->select('payment_id'))
            ->where('payments.state', PaymentStateEnum::CANCELLED)
            ->where('payments.data->bank_transaction_id', $bankTransaction->id)
            ->exists();

        if ($orders->count() > 1) {
            $orderIds = $orders->pluck('id')->all();

            return !$releasedFromThisLine && $this->explainedByHandBankPayments($orders, $bankTransaction)
                ? [BankTransactionMatchStateEnum::ALREADY_PAID, null, ['reason' => 'the orders named carry bank payments of this amount', 'order_ids' => $orderIds]]
                : [BankTransactionMatchStateEnum::REVIEW, null, ['reason' => 'several orders named', 'order_ids' => $orderIds]];
        }

        $order = $orders->first();

        if ($releasedFromThisLine) {
            return [BankTransactionMatchStateEnum::REVIEW, $order, ['reason' => 'a payment from this line on this order was cancelled']];
        }

        if ($order->currency_id !== $bankTransaction->currency_id) {
            return [BankTransactionMatchStateEnum::REVIEW, $order, ['reason' => 'currency differs from the order']];
        }

        $received            = round((float)$bankTransaction->amount, 2);
        $successfulPayments  = $this->successfulPayments($order);
        $sameAmountPaymentId = (clone $successfulPayments)->whereBetween('payments.amount', [$received - 0.01, $received + 0.01])->value('payments.id');
        $secondTransfer      = $sameAmountPaymentId && BankTransaction::where('payment_id', $sameAmountPaymentId)->where('id', '!=', $bankTransaction->id)->exists();

        if ($order->state === OrderStateEnum::CANCELLED) {
            return $this->explainedByHandBankPayments($orders, $bankTransaction)
                ? [BankTransactionMatchStateEnum::ALREADY_PAID, $order, ['reason' => 'a bank payment of this amount is already on the order']]
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

    /**
     * Fulfilment customers pay invoices, not orders. They name the invoice (EUFi221181, often upper-cased or written
     * twice) or only send it: then the payer's name is a fulfilment customer and an invoice of theirs from the last
     * 180 days is exactly this amount. matched means auto-pay can record the payment on the invoice; a line explained
     * by bank payments staff recorded on the invoice by hand is already paid; anything else goes to review.
     *
     * @param list<string> $tokens
     *
     * @return array{0: BankTransactionMatchStateEnum, 1: null, 2: array<string, mixed>}
     */
    public function decideInvoice(BankTransaction $bankTransaction, array $tokens): array
    {
        $fulfilmentShops = Shop::where('organisation_id', $bankTransaction->organisation_id)->where('type', ShopTypeEnum::FULFILMENT)->select('id');

        $invoices = $tokens ? Invoice::where('organisation_id', $bankTransaction->organisation_id)
            ->whereIn('shop_id', $fulfilmentShops)
            ->where('type', InvoiceTypeEnum::INVOICE)
            ->whereIn(DB::raw('upper(reference)'), $tokens)
            ->get() : collect();
        if ($invoices->isNotEmpty()) {
            return $this->decideNamedInvoices($bankTransaction, $invoices);
        }

        $notFound = [BankTransactionMatchStateEnum::UNMATCHED, null, $tokens ? ['reason' => 'order number not found', 'tokens' => $tokens] : ['reason' => 'no order number in the reference']];

        $customerIds = $this->fulfilmentCustomerIds($bankTransaction->organisation_id)[$this->payerKey($bankTransaction->counterparty_name)] ?? [];
        if (!$customerIds) {
            return $notFound;
        }

        $received   = round((float)$bankTransaction->amount, 2);
        $candidates = Invoice::whereIn('customer_id', $customerIds)
            ->whereIn('shop_id', $fulfilmentShops)
            ->where('type', InvoiceTypeEnum::INVOICE)
            ->where('currency_id', $bankTransaction->currency_id)
            ->whereBetween('date', [$bankTransaction->booked_at->copy()->subDays(180), $bankTransaction->booked_at])
            ->whereBetween('total_amount', [$received - 0.01, $received + 0.01])
            ->orderBy('date')
            ->get();
        if ($candidates->isEmpty()) {
            return [BankTransactionMatchStateEnum::UNMATCHED, null, ['reason' => 'fulfilment customer, no invoice of this amount', 'customer_ids' => $customerIds]];
        }
        $candidateIds = $candidates->pluck('id')->all();

        if ($this->releasedFromLine($bankTransaction, $candidateIds)) {
            return [BankTransactionMatchStateEnum::REVIEW, null, ['reason' => 'a payment from this line on this invoice was cancelled', 'invoice_ids' => $candidateIds]];
        }

        $recordedByHand = $candidates->first(fn (Invoice $invoice) => $this->explainedByHandBankPayments(collect([$invoice]), $bankTransaction, 'Invoice', $bankTransaction->booked_at->copy()->subDays(3)));
        if ($recordedByHand) {
            return [BankTransactionMatchStateEnum::ALREADY_PAID, null, ['reason' => 'an invoice of this amount from this payer carries a bank payment recorded by hand', 'invoice_ids' => [$recordedByHand->id], 'matched_by' => 'payer and amount']];
        }

        $owing = $candidates->first(fn (Invoice $invoice) => round((float)$invoice->total_amount - (float)$invoice->payment_amount, 2) > 0.01 && !$this->otherLineMatchedTo($bankTransaction, $invoice));
        if (!$owing) {
            return [BankTransactionMatchStateEnum::REVIEW, null, ['reason' => 'the invoices of this amount from this payer are settled or matched to other lines', 'invoice_ids' => $candidateIds, 'matched_by' => 'payer and amount']];
        }

        return $this->decideOneInvoice($bankTransaction, $owing, ['invoice_ids' => [$owing->id], 'matched_by' => 'payer and amount']);
    }

    /**
     * @param Collection<int, Invoice> $invoices
     *
     * @return array{0: BankTransactionMatchStateEnum, 1: null, 2: array<string, mixed>}
     */
    private function decideNamedInvoices(BankTransaction $bankTransaction, Collection $invoices): array
    {
        $invoiceIds = $invoices->pluck('id')->all();

        if ($this->releasedFromLine($bankTransaction, $invoiceIds)) {
            return [BankTransactionMatchStateEnum::REVIEW, null, ['reason' => 'a payment from this line on this invoice was cancelled', 'invoice_ids' => $invoiceIds]];
        }
        if ($this->explainedByHandBankPayments($invoices, $bankTransaction, 'Invoice')) {
            return [BankTransactionMatchStateEnum::ALREADY_PAID, null, ['reason' => 'the invoices named carry bank payments of this amount', 'invoice_ids' => $invoiceIds]];
        }
        if ($invoices->count() > 1) {
            return [BankTransactionMatchStateEnum::REVIEW, null, ['reason' => 'several invoices named', 'invoice_ids' => $invoiceIds]];
        }

        return $this->decideOneInvoice($bankTransaction, $invoices->first(), ['invoice_ids' => $invoiceIds]);
    }

    /**
     * @param array<string, mixed> $extraMatchData
     *
     * @return array{0: BankTransactionMatchStateEnum, 1: null, 2: array<string, mixed>}
     */
    private function decideOneInvoice(BankTransaction $bankTransaction, Invoice $invoice, array $extraMatchData): array
    {
        $received  = round((float)$bankTransaction->amount, 2);
        $owed      = round((float)$invoice->total_amount - (float)$invoice->payment_amount, 2);
        $matchData = array_merge($extraMatchData, ['invoice_id' => $invoice->id, 'owed' => $owed, 'received' => $received, 'difference' => round($received - $owed, 2)]);

        $reason = match (true) {
            $invoice->currency_id !== $bankTransaction->currency_id => 'currency differs from the invoice',
            $owed <= 0.01 => 'invoice already settled',
            $this->otherLineMatchedTo($bankTransaction, $invoice) => 'another bank line is already matched to this invoice',
            abs($received - $owed) > 0.01 => $received > $owed ? 'pays more than the invoice owes' : 'pays less than the invoice owes',
            default => null,
        };

        return $reason
            ? [BankTransactionMatchStateEnum::REVIEW, null, array_merge($matchData, ['reason' => $reason])]
            : [BankTransactionMatchStateEnum::MATCHED, null, $matchData];
    }

    private function otherLineMatchedTo(BankTransaction $bankTransaction, Invoice $invoice): bool
    {
        return BankTransaction::where('match_state', BankTransactionMatchStateEnum::MATCHED)
            ->where('id', '!=', $bankTransaction->id)
            ->whereNull('payment_id')
            ->where('match_data->invoice_id', (string)$invoice->id)
            ->exists();
    }

    /**
     * @param array<int, int> $invoiceIds
     */
    private function releasedFromLine(BankTransaction $bankTransaction, array $invoiceIds): bool
    {
        return Payment::query()
            ->whereIn('payments.id', DB::table('model_has_payments')->where('model_type', 'Invoice')->whereIn('model_id', $invoiceIds)->select('payment_id'))
            ->where('payments.state', PaymentStateEnum::CANCELLED)
            ->where('payments.data->bank_transaction_id', $bankTransaction->id)
            ->exists();
    }

    /** @var array<int, array<string, list<int>>> */
    private array $fulfilmentCustomerIds = [];

    /**
     * @return array<string, list<int>> fulfilment customer ids by payer key of their company name and of their name
     */
    private function fulfilmentCustomerIds(int $organisationId): array
    {
        return $this->fulfilmentCustomerIds[$organisationId] ??= (function () use ($organisationId) {
            $byKey = [];
            $customers = Customer::where('organisation_id', $organisationId)
                ->whereIn('shop_id', Shop::where('organisation_id', $organisationId)->where('type', ShopTypeEnum::FULFILMENT)->select('id'))
                ->get(['id', 'name', 'company_name']);
            foreach ($customers as $customer) {
                foreach (array_unique(array_filter([$this->payerKey($customer->company_name), $this->payerKey($customer->name)])) as $key) {
                    $byKey[$key][] = $customer->id;
                }
            }

            return $byKey;
        })();
    }

    /**
     * "Remata s. r. o." and "REMATA s.r.o." are the same payer; keys under 4 characters name nobody.
     */
    public function payerKey(?string $name): string
    {
        $key = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string)$name));

        return strlen($key) >= 4 ? $key : '';
    }

    /**
     * Checkout.com pays out the card payments it collected, less its fees, in one transfer per day and currency from
     * Checkout SAS, referenced with its 12 character payout id (some banks put a space inside it). Those payments are
     * already on their orders, so the line pays no order.
     */
    public function cardProcessorPayoutId(BankTransaction $bankTransaction): ?string
    {
        if (!preg_match('/^checkout sas\b/i', (string)$bankTransaction->counterparty_name)) {
            return null;
        }
        $payoutId = strtoupper(str_replace(' ', '', (string)$bankTransaction->reference));

        return preg_match('/^[0-9A-Z]{12}$/', $payoutId) ? $payoutId : null;
    }

    /** PastPay's fees on the payouts it has sent us: 60 and 30 day terms, under each organisation's contract so far. */
    public const array PASTPAY_FEE_RATES = [0.0425, 0.022, 0.046, 0.033, 0.0185, 0.0356, 0.0197, 0.0256];

    /**
     * PastPay pays each order it financed in its own transfer, the order total less its fee, referenced only with our
     * own tax number and the payout date. The order is the one PastPay order from the last 90 days whose total less
     * one of PastPay's fees is what arrived, and that no other payout line has claimed. Its PastPay payment is already
     * on the order, so like a card payout the line pays nothing; it only records which payment it settled.
     *
     * @return array{0: BankTransactionMatchStateEnum, 1: Order|null, 2: array<string, mixed>}
     */
    private function decidePastpayPayout(BankTransaction $bankTransaction): array
    {
        $received   = round((float)$bankTransaction->amount, 2);
        $candidates = Payment::query()
            ->join('payment_accounts', 'payment_accounts.id', 'payments.payment_account_id')
            ->join('model_has_payments', fn ($join) => $join->on('model_has_payments.payment_id', 'payments.id')->where('model_has_payments.model_type', 'Order'))
            ->join('orders', 'orders.id', 'model_has_payments.model_id')
            ->where('payment_accounts.type', PaymentAccountTypeEnum::PASTPAY)
            ->where('payments.organisation_id', $bankTransaction->organisation_id)
            ->where('payments.currency_id', $bankTransaction->currency_id)
            ->where('payments.status', PaymentStatusEnum::SUCCESS)
            ->whereNot('payments.state', PaymentStateEnum::CANCELLED)
            ->where('payments.amount', '>', 0)
            ->whereBetween('payments.date', [$bankTransaction->booked_at->copy()->subDays(90), $bankTransaction->booked_at])
            ->get(['payments.id', 'payments.payment_account_id', 'orders.id as order_id', 'orders.total_amount'])
            ->map(function (Payment $payment) use ($received) {
                $rate = collect(self::PASTPAY_FEE_RATES)->first(fn (float $rate) => abs(round((float)$payment->total_amount * (1 - $rate), 2) - $received) <= 0.01);

                return $rate === null ? null : ['payment' => $payment, 'rate' => $rate];
            })
            ->filter()
            ->reject(fn (array $candidate) => BankTransaction::where('match_state', BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT)
                ->where('id', '!=', $bankTransaction->id)
                ->where('match_data->provider', 'pastpay')
                ->whereJsonContains('match_data->payment_ids', $candidate['payment']->id)
                ->exists())
            ->unique(fn (array $candidate) => $candidate['payment']->id)
            ->values();

        if ($candidates->count() !== 1) {
            return [BankTransactionMatchStateEnum::UNMATCHED, null, [
                'reason'      => $candidates->isEmpty() ? 'PastPay payout, no PastPay order of this amount' : 'PastPay payout, several PastPay orders of this amount',
                'payment_ids' => $candidates->pluck('payment.id')->all(),
            ]];
        }

        ['payment' => $payment, 'rate' => $rate] = $candidates->first();
        $gross = round((float)$payment->total_amount, 2);

        return [BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT, Order::find($payment->order_id), [
            'provider'           => 'pastpay',
            'reason'             => null,
            'reconciled_at'      => now()->toIso8601String(),
            'fee_rate'           => $rate,
            'payout_total'       => $received,
            'gross'              => $gross,
            'processor_fees'     => round($gross - $received, 2),
            'bank_fee'           => 0,
            'payment_ids'        => [$payment->id],
            'payments_in_payout' => 1,
            'refunds_in_payout'  => 0,
            'payments_missing'   => [],
            'payment_account_id' => $payment->payment_account_id,
        ]];
    }

    /**
     * Faire pays each order in its own transfer, referenced "Faire <order code>".
     */
    public function marketplaceOrderReference(BankTransaction $bankTransaction): ?string
    {
        if (!preg_match('/^faire\b/i', (string)$bankTransaction->counterparty_name)) {
            return null;
        }

        return preg_match('/(?<![A-Za-z0-9])Faire ([A-Za-z0-9]{8,12})(?![A-Za-z0-9])/i', (string)$bankTransaction->reference, $matches)
            ? strtoupper($matches[1])
            : null;
    }

    /**
     * A marketplace payout pays its order whatever the amount: the marketplace decides what it pays. No customer
     * payment is recorded, as the retailer paid the marketplace. The difference from the order total less the
     * commission Aiku stores is kept on the line, to report payouts that came in short.
     *
     * @return array{0: BankTransactionMatchStateEnum, 1: Order|null, 2: array<string, mixed>}
     */
    private function decideMarketplacePayout(BankTransaction $bankTransaction, string $orderReference): array
    {
        $order = Order::where('organisation_id', $bankTransaction->organisation_id)
            ->where('reference', $orderReference)
            ->whereHas('shop', fn ($query) => $query->where('type', ShopTypeEnum::EXTERNAL))
            ->get()
            ->first(fn (Order $order) => $order->shop->isFaire());
        if (!$order) {
            return [BankTransactionMatchStateEnum::UNMATCHED, null, ['reason' => 'marketplace order not found', 'tokens' => [$orderReference]]];
        }

        $received  = round((float)$bankTransaction->amount, 2);
        $owed      = round((float)$order->total_amount - (float)$order->commission_amount, 2);
        $matchData = ['owed' => $owed, 'received' => $received, 'difference' => round($received - $owed, 2), 'commission' => round((float)$order->commission_amount, 2)];

        $otherPayout = BankTransaction::where('order_id', $order->id)
            ->where('id', '!=', $bankTransaction->id)
            ->where('match_state', BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE)
            ->exists();

        $reason = match (true) {
            $order->currency_id !== $bankTransaction->currency_id => 'currency differs from the order',
            $order->state === OrderStateEnum::CANCELLED => 'order cancelled',
            $otherPayout => 'another marketplace payout is already on this order',
            default => null,
        };

        return $reason
            ? [BankTransactionMatchStateEnum::REVIEW, $order, array_merge($matchData, ['reason' => $reason])]
            : [BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE, $order, $matchData];
    }

    /**
     * A Faire order reads as paid by the marketplace only while a payout line is linked to it, so it is worked out
     * again whenever a line is linked or its decision is forgotten.
     *
     * @param array<int, int|null> $orderIds
     */
    public function refreshMarketplaceOrders(array $orderIds): void
    {
        foreach (Order::whereIn('id', array_filter($orderIds))->get() as $order) {
            try {
                UpdateOrderPaymentsStatus::run($order);
            } catch (Throwable $exception) {
                Sentry::captureException($exception);
            }
        }
    }

    private function successfulPayments(Order $order): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $order->payments()
            ->where('payments.status', PaymentStatusEnum::SUCCESS)
            ->whereNot('payments.state', PaymentStateEnum::CANCELLED)
            ->whereHas('paymentAccount', fn ($query) => $query->whereNot('type', PaymentAccountTypeEnum::BANK_FEE));
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
            $forgottenMarketplaceOrderIds = [];
            if ($command->option('reset')) {
                $forgottenMarketplaceOrderIds = BankTransaction::where('bank_connection_id', $bankConnection->id)
                    ->where('match_state', BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE)
                    ->whereNull('payment_id')
                    ->pluck('order_id')
                    ->all();
                BankTransaction::where('bank_connection_id', $bankConnection->id)
                    ->whereNull('payment_id')
                    ->where(fn ($query) => $query->whereNull('match_state')->orWhere('match_state', '!=', BankTransactionMatchStateEnum::DISMISSED))
                    ->update(['match_state' => null, 'order_id' => null, 'match_data' => '{}', 'matched_at' => null]);
            }

            if ($command->option('unmatched')) {
                BankTransaction::where('bank_connection_id', $bankConnection->id)
                    ->whereNull('payment_id')
                    ->where('match_state', BankTransactionMatchStateEnum::UNMATCHED)
                    ->update(['match_state' => null, 'order_id' => null, 'match_data' => '{}', 'matched_at' => null]);
            }

            foreach ($this->handle($bankConnection) as $state => $lines) {
                $command->info("$state: $lines");
            }
            $this->refreshMarketplaceOrders($forgottenMarketplaceOrderIds);
        } finally {
            $lock->release();
        }

        return 0;
    }
}
