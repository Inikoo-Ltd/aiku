<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\OrgAction;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Exceptions\CheckoutRefundOutcomeUnknown;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\CreditTransaction;
use App\Models\Accounting\Payment;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Sentry;
use Throwable;

/**
 * Sends a customer's balance back to the cards that paid it, as much as can be: each Checkout.com payment gives
 * back only what of it is still in the credit, newest first, never more than the balance less the credit we
 * gave, so the balance never goes negative and gifts never leave as cash. What cannot go back automatically stays
 * in the balance for staff to pay another way.
 */
class RefundCustomerBalanceByApi extends OrgAction
{
    public const int LOOKBACK_DAYS = 365;

    private Customer $customer;

    public static function ledgerBalance(Customer $customer): float
    {
        return round((float) CreditTransaction::where('customer_id', $customer->id)->sum('amount'), 2);
    }

    /**
     * Credit we gave (compensation, added funds, transfers in, positive adjustments, top ups not paid by card) in the
     * lookback: it is taken as still in the balance, so it never goes out as cash to a card.
     */
    public static function giftedCredit(Customer $customer): float
    {
        $since = now()->subDays(self::LOOKBACK_DAYS);

        $gifted = (float) CreditTransaction::where('customer_id', $customer->id)
            ->where('date', '>=', $since)
            ->where('amount', '>', 0)
            ->whereIn('type', [
                CreditTransactionTypeEnum::COMPENSATION,
                CreditTransactionTypeEnum::ADD_FUNDS_OTHER,
                CreditTransactionTypeEnum::TRANSFER_IN,
                CreditTransactionTypeEnum::ADJUST,
            ])
            ->sum('amount');

        $topUpsNotByCard = (float) CreditTransaction::where('customer_id', $customer->id)
            ->where('date', '>=', $since)
            ->where('amount', '>', 0)
            ->where('type', CreditTransactionTypeEnum::TOP_UP)
            ->where(fn ($query) => $query->whereNull('payment_id')->orWhereNotIn('payment_id', Payment::select('payments.id')
                ->join('payment_accounts', 'payment_accounts.id', '=', 'payments.payment_account_id')
                ->where('payment_accounts.type', PaymentAccountTypeEnum::CHECKOUT)))
            ->sum('amount');

        return round($gifted + $topUpsNotByCard, 2);
    }

    /**
     * Card payments whose money can sit in the credit: a top up, or a payment of orders that were cancelled or
     * put an excess in the credit. The per payment amount is RefundPaymentByApi::creditRefundableLeft.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Payment>
     */
    private static function candidates(Customer $customer): \Illuminate\Database\Eloquent\Collection
    {
        $orderIdsWithCredit = DB::table('model_has_payments as order_payments')
            ->join('orders', 'orders.id', '=', 'order_payments.model_id')
            ->where('order_payments.model_type', 'Order')
            ->where('orders.customer_id', $customer->id)
            ->where(fn ($query) => $query->where('orders.state', OrderStateEnum::CANCELLED->value)
                ->orWhereExists(fn ($exists) => $exists->select(DB::raw(1))
                    ->from('model_has_payments as credited')
                    ->join('credit_transactions', 'credit_transactions.payment_id', '=', 'credited.payment_id')
                    ->whereColumn('credited.model_id', 'orders.id')
                    ->where('credited.model_type', 'Order')
                    ->where('credit_transactions.type', CreditTransactionTypeEnum::FROM_EXCESS->value)))
            ->select('orders.id');

        return Payment::where('customer_id', $customer->id)
            ->where('type', PaymentTypeEnum::PAYMENT)
            ->where('status', PaymentStatusEnum::SUCCESS)
            ->whereNot('state', PaymentStateEnum::CANCELLED)
            ->where('reference', 'like', 'pay\_%')
            ->where('date', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->whereHas('paymentAccount', fn ($query) => $query->where('type', PaymentAccountTypeEnum::CHECKOUT))
            ->where(fn ($query) => $query
                ->whereIn('id', CreditTransaction::where('customer_id', $customer->id)->where('type', CreditTransactionTypeEnum::TOP_UP)->whereNotNull('payment_id')->select('payment_id'))
                ->orWhereHas('orders', fn ($orders) => $orders->whereIn('orders.id', $orderIdsWithCredit)))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
    }

    private static function poolKey(Payment $payment): string
    {
        $orderIds = $payment->orders()->orderBy('orders.id')->pluck('orders.id')->implode(',');

        return $orderIds === '' ? 'payment:'.$payment->id : 'orders:'.$orderIds;
    }

    /**
     * @return array{balance: float, gifted: float, refunds: list<array{payment_id: int, reference: string, date: string|null, method: string|null, amount: float}>, to_card: float, stays: float}
     */
    public static function plan(Customer $customer): array
    {
        $balance   = self::ledgerBalance($customer);
        $gifted    = $balance > 0 ? self::giftedCredit($customer) : 0;
        $remaining = round(max(0, $balance - $gifted), 2);
        $refunds   = [];

        if ($remaining > 0) {
            $rows = self::candidates($customer)
                ->map(fn (Payment $payment) => ['payment' => $payment, 'left' => RefundPaymentByApi::creditRefundableLeft($payment), 'pool' => self::poolKey($payment)])
                ->filter(fn (array $row) => $row['left'] > 0);

            $poolBudget = $rows->groupBy('pool')->map(fn ($pool) => $pool->max('left'))->all();

            foreach ($rows as $row) {
                if ($remaining <= 0) {
                    break;
                }
                $amount = round(min($row['left'], $poolBudget[$row['pool']], $remaining), 2);
                if ($amount <= 0) {
                    continue;
                }
                $payment   = $row['payment'];
                $refunds[] = [
                    'payment_id' => $payment->id,
                    'reference'  => $payment->reference,
                    'date'       => $payment->date?->toDateString(),
                    'method'     => $payment->method,
                    'amount'     => $amount,
                ];
                $poolBudget[$row['pool']] = round($poolBudget[$row['pool']] - $amount, 2);
                $remaining                = round($remaining - $amount, 2);
            }
        }

        $toCard = round(array_sum(array_column($refunds, 'amount')), 2);

        return [
            'balance' => $balance,
            'gifted'  => $gifted,
            'refunds' => $refunds,
            'to_card' => $toCard,
            'stays'   => round(max(0, $balance) - $toCard, 2),
        ];
    }

    /**
     * Each refund is its own step with its own locks and caps: one refused by Checkout.com leaves its money in the
     * balance and the others still go. Never more than staff confirmed. An unclear answer from Checkout.com, or
     * any error, stops the run so nothing more leaves until someone has looked.
     *
     * @return array{refunded: list<array<string, mixed>>, failed: list<array<string, mixed>>, unknown: list<array<string, mixed>>, stopped: bool, to_card: float, balance: float}
     */
    public function handle(Customer $customer, float $confirmed): array
    {
        $refunded = [];
        $failed   = [];
        $unknown  = [];
        $stopped  = false;
        $budget   = round($confirmed, 2);

        foreach (self::plan($customer)['refunds'] as $planned) {
            $payment = Payment::find($planned['payment_id']);
            $amount  = $payment ? round(min($planned['amount'], $budget, RefundPaymentByApi::creditRefundableLeft($payment), self::ledgerBalance($customer)), 2) : 0;
            if (!$payment || $amount <= 0) {
                continue;
            }

            $step = ['payment_id' => $payment->id, 'reference' => $payment->reference, 'amount' => $amount];
            try {
                $refundPayment = RefundPaymentByApi::make()->handle($payment, ['amount' => $amount, 'from_credit' => true]);
                $refunded[]    = [...$step, 'action_id' => (string) $refundPayment->reference];
                $budget        = round($budget - $amount, 2);
            } catch (CheckoutRefundOutcomeUnknown $e) {
                $unknown[] = [...$step, 'reason' => implode(' ', Arr::flatten($e->errors()))];
                $stopped   = true;
                break;
            } catch (ValidationException $e) {
                $failed[] = [...$step, 'reason' => implode(' ', Arr::flatten($e->errors()))];
            } catch (Throwable $e) {
                Sentry::captureException($e);
                $failed[] = [...$step, 'reason' => __('Unexpected error, nothing more was sent')];
                $stopped  = true;
                break;
            }
        }

        return [
            'refunded' => $refunded,
            'failed'   => $failed,
            'unknown'  => $unknown,
            'stopped'  => $stopped,
            'to_card'  => round(array_sum(array_column($refunded, 'amount')), 2),
            'balance'  => self::ledgerBalance($customer),
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            "crm.{$this->customer->shop_id}.edit",
            "accounting.{$this->customer->organisation_id}.edit",
        ]);
    }

    public function rules(): array
    {
        return [
            'confirmed_amount' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Customer $customer, ActionRequest $request): array
    {
        $this->customer = $customer;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, (float) $this->validatedData['confirmed_amount']);
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(Customer $customer, ActionRequest $request): array
    {
        $this->customer = $customer;
        $this->initialisationFromShop($customer->shop, $request);

        return self::plan($customer);
    }
}
