<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Invoice\AttachPaymentToInvoice;
use App\Actions\Accounting\Payment\Traits\HydratesPaymentSideEffects;
use App\Actions\Accounting\Invoice\UpdateInvoicePaymentState;
use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\CreditTransaction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\CRM\Customer;
use Checkout\CheckoutApiException;
use Checkout\Payments\PaymentsClient;
use Checkout\Payments\RefundRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Sentry;

class RefundPaymentByApi extends OrgAction
{
    use WithActionUpdate;
    use WithCheckoutCom;
    use HydratesPaymentSideEffects;

    private Payment $payment;

    /**
     * What was refunded from this payment: the stored total, or the refunds recorded against it when they
     * add up to more (some old payments kept a total of 0 after being refunded).
     */
    public static function alreadyRefunded(Payment $payment): float
    {
        $recorded = abs((float) Payment::where('original_payment_id', $payment->id)
            ->where('type', PaymentTypeEnum::REFUND)
            ->where('status', PaymentStatusEnum::SUCCESS)
            ->whereNot('state', PaymentStateEnum::CANCELLED)
            ->sum('amount'));

        return round(max((float) $payment->total_refund, $recorded), 2);
    }

    public static function canRefundByApi(?string $paymentAccountType, ?string $reference): bool
    {
        return $paymentAccountType === PaymentAccountTypeEnum::CHECKOUT->value && str_starts_with((string) $reference, 'pay_');
    }

    /**
     * The key depends only on what was refunded and declined before and on this amount, so a retry after a rolled back
     * attempt sends the same key and Checkout.com answers with the refund it already made instead of paying again,
     * while a new attempt after Checkout.com declined one gets a new key and is really sent.
     */
    public static function idempotencyKey(Payment $payment, float $amount): string
    {
        $declined = Payment::where('original_payment_id', $payment->id)->where('type', PaymentTypeEnum::REFUND)->where('status', PaymentStatusEnum::FAIL)->count();

        return 'aiku-refund-'.$payment->id.'-'.(int) round((float) $payment->total_refund * 100).'-'.(int) round($amount * 100).'-'.$declined;
    }

    /**
     * Money goes back to the card only from where it provably is: a refund invoice of an invoice this payment paid,
     * or the customer credit when the payment was a top up, the excess of an invoice, or paid orders that were all cancelled.
     *
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Payment $payment, array $modelData): Payment
    {
        $refundAmount = round((float) Arr::get($modelData, 'amount'), 2);
        if ($refundAmount <= 0) {
            throw ValidationException::withMessages(['amount' => __('The amount must be at least 0.01')]);
        }

        if (Arr::get($modelData, 'from_credit')) {
            return $this->refundFromCredit($payment, $refundAmount);
        }

        $invoice = Invoice::where('id', Arr::get($modelData, 'invoice_id'))->where('type', InvoiceTypeEnum::REFUND)->where('in_process', false)->first();
        if (!$invoice) {
            throw ValidationException::withMessages(['invoice_id' => __('A refund by API needs the refund invoice it pays')]);
        }
        if (!$payment->invoices()->where('invoices.id', $invoice->original_invoice_id)->exists()) {
            throw ValidationException::withMessages(['invoice_id' => __('This refund is not of an invoice this payment paid')]);
        }

        return DB::transaction(function () use ($refundAmount, $payment, $invoice) {
            $payment       = RefundPaymentToBalance::lockForRefund($payment, $invoice, $refundAmount);
            $idempotentKey = self::idempotencyKey($payment, $refundAmount);
            $refundPayment = $this->storeRefundPayment($payment, $refundAmount);

            AttachPaymentToInvoice::make()->action($invoice, $refundPayment, []);
            if ($invoice->order) {
                AttachPaymentToOrder::make()->action($invoice->order, $refundPayment, []);
            }

            $refundPayment->update(['reference' => $this->refundAtCheckout($payment, $refundAmount, $invoice->reference, $idempotentKey)]);

            return $refundPayment;
        });
    }

    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    private function refundFromCredit(Payment $payment, float $refundAmount): Payment
    {
        return DB::transaction(function () use ($refundAmount, $payment) {
            $payment  = self::lockPaymentForApiRefund($payment, $refundAmount);
            $customer = Customer::lockForUpdate()->findOrFail($payment->customer_id);

            $creditLeft = self::creditRefundableLeft($payment);
            $balance    = round((float) CreditTransaction::where('customer_id', $customer->id)->sum('amount'), 2);
            if ($creditLeft <= 0) {
                throw ValidationException::withMessages(['amount' => __('The money of this payment is not in the customer credit')]);
            }
            if ($refundAmount > min($creditLeft, $balance)) {
                throw ValidationException::withMessages(['amount' => __('The most that can be refunded from the customer credit is :amount', ['amount' => number_format(max(0, min($creditLeft, $balance)), 2)])]);
            }

            $idempotentKey = self::idempotencyKey($payment, $refundAmount);
            $refundPayment = $this->storeRefundPayment($payment, $refundAmount);

            StoreCreditTransaction::make()->action($customer, [
                'amount'     => -$refundAmount,
                'date'       => now(),
                'type'       => CreditTransactionTypeEnum::MONEY_BACK,
                'payment_id' => $refundPayment->id,
                'notes'      => 'Refunded to '.($payment->method ?: 'the original payment').' through Checkout.com',
            ], notifyCustomer: false);

            $refundPayment->update(['reference' => $this->refundAtCheckout($payment, $refundAmount, $payment->reference, $idempotentKey)]);

            return $refundPayment;
        });
    }

    private function storeRefundPayment(Payment $payment, float $refundAmount): Payment
    {
        $refundPayment = StorePayment::make()->action($payment->customer, $payment->paymentAccount, array_filter([
            'amount'                  => -$refundAmount,
            'status'                  => PaymentStatusEnum::SUCCESS->value,
            'state'                   => PaymentStateEnum::COMPLETED->value,
            'type'                    => PaymentTypeEnum::REFUND,
            'original_payment_id'     => $payment->id,
            'payment_account_shop_id' => $payment->payment_account_shop_id,
            'data'                    => ['refunded_by_api' => true],
            'source'                  => ['type' => $payment->method, 'scheme' => $payment->sub_method],
        ], fn ($value) => $value !== null));

        $this->update($payment, [
            'total_refund' => $payment->total_refund + $refundAmount,
            'with_refund'  => true
        ]);

        return $refundPayment;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function lockPaymentForApiRefund(Payment $payment, float $amount): Payment
    {
        $payment = Payment::lockForUpdate()->findOrFail($payment->id);

        if ($payment->type !== PaymentTypeEnum::PAYMENT || $payment->status !== PaymentStatusEnum::SUCCESS || $payment->state === PaymentStateEnum::CANCELLED) {
            throw ValidationException::withMessages(['amount' => __('Only a successful payment can be refunded')]);
        }
        if (!self::canRefundByApi($payment->paymentAccount->type->value, $payment->reference)) {
            throw ValidationException::withMessages(['amount' => __('Only Checkout.com payments can be refunded by API')]);
        }
        if (round($amount, 2) > round((float) $payment->amount - self::alreadyRefunded($payment), 2)) {
            throw ValidationException::withMessages(['amount' => __('The amount is more than is left to refund on this payment')]);
        }

        return $payment;
    }

    private static function onlyCancelledOrders(Payment $payment): bool
    {
        return !$payment->invoices()->where('invoices.type', InvoiceTypeEnum::INVOICE)->exists()
            && $payment->orders()->exists()
            && !$payment->orders()->where('orders.state', '!=', OrderStateEnum::CANCELLED)->exists();
    }

    /**
     * What the payment page offers: the refund invoices still owed and the customer credit that can be sent back
     * to the card, or why neither can.
     *
     * @return array<string, mixed>|null
     */
    public static function refundOptions(Payment $payment): ?array
    {
        $leftOnPayment = round((float) $payment->amount - self::alreadyRefunded($payment), 2);
        if ($payment->type !== PaymentTypeEnum::PAYMENT || $payment->status !== PaymentStatusEnum::SUCCESS || $payment->state === PaymentStateEnum::CANCELLED || $leftOnPayment <= 0
            || !self::canRefundByApi($payment->paymentAccount?->type?->value, $payment->reference)) {
            return null;
        }

        $refundInvoices = Invoice::where('type', InvoiceTypeEnum::REFUND)
            ->where('in_process', false)
            ->whereIn('original_invoice_id', $payment->invoices()->where('invoices.type', InvoiceTypeEnum::INVOICE)->select('invoices.id'))
            ->orderBy('id')
            ->get()
            ->map(fn (Invoice $refund) => [
                'id'        => $refund->id,
                'reference' => $refund->reference,
                'left'      => round(abs((float) $refund->total_amount) - abs((float) $refund->payment_amount), 2),
            ])
            ->filter(fn (array $refund) => $refund['left'] > 0)
            ->map(fn (array $refund) => [...$refund, 'max' => min($refund['left'], $leftOnPayment)])
            ->values()
            ->all();

        $creditLeft = self::creditRefundableLeft($payment);
        $balance    = round((float) CreditTransaction::where('customer_id', $payment->customer_id)->sum('amount'), 2);
        $credit     = $creditLeft > 0 && $balance > 0 ? [
            'balance' => $balance,
            'max'     => round(min($creditLeft, $balance, $leftOnPayment), 2),
        ] : null;

        $blocked = null;
        if (!$refundInvoices && !$credit) {
            $blocked = match (true) {
                $creditLeft > 0 => __('The customer credit is :balance, nothing left of this payment to send back from it', ['balance' => number_format($balance, 2)]),
                $payment->invoices()->where('invoices.type', InvoiceTypeEnum::INVOICE)->exists() => __('Make the refund invoice first: money goes back to the card against a refund invoice'),
                $payment->orders()->exists() => __('The order is still open: cancel it first, its money then goes to the customer credit and can be sent back from there'),
                default => __('This payment is not linked to an invoice, an order or the customer credit'),
            };
        }

        return [
            'left_on_payment' => $leftOnPayment,
            'refund_invoices' => $refundInvoices,
            'credit'          => $credit,
            'blocked'         => $blocked,
            'route'           => [
                'name'       => 'grp.models.payment.refund_api',
                'parameters' => ['payment' => $payment->id],
            ],
        ];
    }

    /**
     * What of this payment sits in the customer credit and was not refunded from there yet: the credit its top up
     * added, or, when it paid only orders that were all cancelled, what those cancellations put in the credit
     * (CancelOrder records it as account refunds on the orders, less any deposit kept) net of what card payments
     * of those orders already sent back from it.
     */
    public static function creditRefundableLeft(Payment $payment): float
    {
        $leftOnPayment = (float) $payment->amount - self::alreadyRefunded($payment);

        $topUp = (float) CreditTransaction::where('payment_id', $payment->id)
            ->where('type', CreditTransactionTypeEnum::TOP_UP)
            ->where('amount', '>', 0)
            ->sum('amount');
        if ($topUp > 0) {
            return max(0, round(min($topUp - self::refundedFromCredit([$payment->id]), $leftOnPayment), 2));
        }

        if ($payment->invoices()->where('invoices.type', InvoiceTypeEnum::INVOICE)->exists()
            && $payment->orders()->whereNotNull('orders.data->excess_refund')->exists()) {
            return self::invoicedExcessLeft($payment, $leftOnPayment);
        }

        if (!self::onlyCancelledOrders($payment)) {
            return 0;
        }

        $orderIds = $payment->orders()->pluck('orders.id');

        $creditedByCancel = abs((float) Payment::whereHas('orders', fn ($query) => $query->whereIn('orders.id', $orderIds))
            ->where('type', PaymentTypeEnum::REFUND)
            ->where('status', PaymentStatusEnum::SUCCESS)
            ->whereNot('state', PaymentStateEnum::CANCELLED)
            ->whereHas('paymentAccount', fn ($query) => $query->where('type', PaymentAccountTypeEnum::ACCOUNT))
            ->sum('amount'));

        $cardPaymentIds = Payment::whereHas('orders', fn ($query) => $query->whereIn('orders.id', $orderIds))
            ->where('type', PaymentTypeEnum::PAYMENT)
            ->pluck('id')
            ->all();

        return max(0, round(min($creditedByCancel - self::refundedFromCredit($cardPaymentIds), $leftOnPayment), 2));
    }

    /**
     * What invoicing put in the customer credit for items not shipped in the orders this payment paid (the
     * FROM_EXCESS credit of their account refund payments), net of what card payments of those orders already
     * sent back from the credit, and never more than is left on this payment.
     */
    private static function invoicedExcessLeft(Payment $payment, float $leftOnPayment): float
    {
        $orderIds = $payment->orders()->pluck('orders.id');
        if ($orderIds->isEmpty()) {
            return 0;
        }

        $excessPaymentIds = Payment::whereHas('orders', fn ($query) => $query->whereIn('orders.id', $orderIds))
            ->where('type', PaymentTypeEnum::REFUND)
            ->where('status', PaymentStatusEnum::SUCCESS)
            ->whereNot('state', PaymentStateEnum::CANCELLED)
            ->whereHas('paymentAccount', fn ($query) => $query->where('type', PaymentAccountTypeEnum::ACCOUNT))
            ->pluck('id');

        $excess = (float) CreditTransaction::whereIn('payment_id', $excessPaymentIds)
            ->where('type', CreditTransactionTypeEnum::FROM_EXCESS)
            ->where('amount', '>', 0)
            ->sum('amount');

        $cardPaymentIds = Payment::whereHas('orders', fn ($query) => $query->whereIn('orders.id', $orderIds))
            ->where('type', PaymentTypeEnum::PAYMENT)
            ->pluck('id')
            ->all();

        return max(0, round(min($excess - self::refundedFromCredit($cardPaymentIds), $leftOnPayment), 2));
    }

    /**
     * @param  array<int>  $paymentIds
     */
    private static function refundedFromCredit(array $paymentIds): float
    {
        return abs((float) CreditTransaction::whereIn(
            'payment_id',
            Payment::whereIn('original_payment_id', $paymentIds)->where('type', PaymentTypeEnum::REFUND)->where('status', PaymentStatusEnum::SUCCESS)->select('id')
        )->where('type', CreditTransactionTypeEnum::MONEY_BACK)->sum('amount'));
    }

    /**
     * Asks Checkout.com to send the money back to what the customer paid with; returns its refund action id.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function refundAtCheckout(Payment $payment, float $amount, string $reference, string $idempotencyKey): string
    {
        if (!self::canRefundByApi($payment->paymentAccount->type->value, $payment->reference)) {
            throw ValidationException::withMessages(['amount' => __('Only Checkout.com payments can be refunded by API')]);
        }

        $paymentAccountShop = $payment->paymentAccountShop
            ?? PaymentAccountShop::where('payment_account_id', $payment->payment_account_id)->where('shop_id', $payment->shop_id)->first();

        $paymentsClient = $this->checkoutPaymentsClient($paymentAccountShop);
        if (!$paymentsClient) {
            throw ValidationException::withMessages(['amount' => __('Checkout.com is not reachable for this payment account')]);
        }

        $refundRequest            = new RefundRequest();
        $refundRequest->amount    = (int) round($amount * 100);
        $refundRequest->reference = $reference;

        try {
            $response = $paymentsClient->refundPayment($payment->reference, $refundRequest, $idempotencyKey);
        } catch (CheckoutApiException $e) {
            if (!$e->http_metadata) {
                Sentry::captureException($e);
                throw ValidationException::withMessages(['amount' => __('No answer from Checkout.com, so the refund may or may not have been made: check the payment in Checkout.com before trying again')]);
            }
            $codes = implode(', ', Arr::get($e->error_details ?? [], 'error_codes', []));
            throw ValidationException::withMessages(['amount' => __('Checkout.com refused the refund: :reason', ['reason' => $codes ?: $e->getMessage()])]);
        }

        $actionId = (string) Arr::get($response, 'action_id');
        if ($actionId === '') {
            Sentry::captureMessage("Checkout.com refund of {$payment->reference} answered without action_id");
            throw ValidationException::withMessages(['amount' => __('Checkout.com accepted the refund without a reference: check the payment in Checkout.com before trying again')]);
        }

        return $actionId;
    }

    public function checkoutPaymentsClient(?PaymentAccountShop $paymentAccountShop): ?PaymentsClient
    {
        [$publicKey, $secretKey] = ($paymentAccountShop?->getCredentials() ?? []) + [null, null];

        return $publicKey && $secretKey ? $this->getCheckoutApi($publicKey, $secretKey)?->getPaymentsClient() : null;
    }

    /**
     * Checkout.com refunds are confirmed later; when one is declined (e.g. not enough balance) the refund
     * we recorded never reached the customer, so it stops counting on the payment, the invoice and the order.
     *
     * @throws \Throwable
     */
    public static function undoDeclinedRefund(string $actionId): ?Payment
    {
        $refundPayment = DB::transaction(function () use ($actionId) {
            $refundPayment = Payment::where('reference', $actionId)
                ->where('type', PaymentTypeEnum::REFUND)
                ->where('status', PaymentStatusEnum::SUCCESS)
                ->lockForUpdate()
                ->first();
            if (!$refundPayment) {
                return null;
            }

            $refundPayment->update(['status' => PaymentStatusEnum::FAIL, 'state' => PaymentStateEnum::DECLINED]);

            if ($original = Payment::lockForUpdate()->find($refundPayment->original_payment_id)) {
                $totalRefund = max(0, round((float) $original->total_refund - abs((float) $refundPayment->amount), 2));
                $original->update(['total_refund' => $totalRefund, 'with_refund' => $totalRefund > 0]);
            }

            $takenFromCredit = CreditTransaction::where('payment_id', $refundPayment->id)->where('type', CreditTransactionTypeEnum::MONEY_BACK)->sum('amount');
            if ($takenFromCredit < 0) {
                StoreCreditTransaction::make()->action($refundPayment->customer, [
                    'amount'     => abs((float) $takenFromCredit),
                    'date'       => now(),
                    'type'       => CreditTransactionTypeEnum::MONEY_BACK,
                    'payment_id' => $refundPayment->id,
                    'notes'      => "Checkout.com declined refund $actionId: money put back in the credit",
                ], notifyCustomer: false);
            }

            foreach ($refundPayment->invoices as $invoice) {
                UpdateInvoicePaymentState::run($invoice);
            }
            foreach ($refundPayment->orders as $order) {
                UpdateOrderPaymentsStatus::run($order);
            }

            Sentry::captureMessage("Checkout.com declined refund $actionId (payment $refundPayment->id): reversed in aiku, the customer was not paid");

            return $refundPayment;
        });

        if ($refundPayment) {
            self::make()->hydratePaymentSideEffects($refundPayment);
        }

        return $refundPayment;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            "crm.{$this->payment->shop_id}.edit",
            "accounting.{$this->payment->organisation_id}.edit",
        ]);
    }

    public function rules(): array
    {
        return [
            'amount'      => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'lte:'.$this->payment->amount - $this->payment->total_refund],
            'from_credit' => ['sometimes', 'boolean'],
            'invoice_id'  => [
                'required_without:from_credit',
                'nullable',
                Rule::exists('invoices', 'id')
                    ->where('customer_id', $this->payment->customer_id)
                    ->where('type', InvoiceTypeEnum::REFUND->value)
                    ->whereNull('deleted_at')
            ]
        ];
    }

    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Payment $payment, ActionRequest $request): Payment
    {
        $this->payment = $payment;
        $this->initialisation($payment->organisation, $request);

        return $this->handle($payment, $this->validatedData);
    }
}
