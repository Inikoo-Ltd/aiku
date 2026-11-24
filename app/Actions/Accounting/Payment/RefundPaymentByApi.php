<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\Invoice\AttachPaymentToInvoice;
use App\Actions\Accounting\Payment\Traits\HydratesPaymentSideEffects;
use App\Actions\Accounting\Invoice\UpdateInvoicePaymentState;
use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccountShop;
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

    public static function canRefundByApi(?string $paymentAccountType, ?string $reference): bool
    {
        return $paymentAccountType === PaymentAccountTypeEnum::CHECKOUT->value && str_starts_with((string) $reference, 'pay_');
    }

    /**
     * The key depends only on what was refunded before and on this amount, so a retry after a rolled back
     * attempt sends the same key and Checkout.com answers with the refund it already made instead of paying again.
     */
    public static function idempotencyKey(Payment $payment, float $amount): string
    {
        return 'aiku-refund-'.$payment->id.'-'.(int) round((float) $payment->total_refund * 100).'-'.(int) round($amount * 100);
    }

    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Payment $payment, array $modelData): Payment
    {
        $refundAmount = round((float) Arr::get($modelData, 'amount'), 2);
        if ($refundAmount <= 0) {
            throw ValidationException::withMessages(['amount' => __('The amount must be at least 0.01')]);
        }

        $invoice = Invoice::where('id', Arr::get($modelData, 'invoice_id'))->where('type', InvoiceTypeEnum::REFUND)->first();
        if (!$invoice) {
            throw ValidationException::withMessages(['invoice_id' => __('A refund by API needs the refund invoice it pays')]);
        }

        return DB::transaction(function () use ($refundAmount, $payment, $invoice) {
            $payment = RefundPaymentToBalance::lockForRefund($payment, $invoice, $refundAmount);

            $actionId = $this->refundAtCheckout($payment, $refundAmount, $invoice, self::idempotencyKey($payment, $refundAmount));

            $refundPayment = StorePayment::make()->action($payment->customer, $payment->paymentAccount, array_filter([
                'amount'                  => -$refundAmount,
                'reference'               => $actionId,
                'status'                  => PaymentStatusEnum::SUCCESS->value,
                'state'                   => PaymentStateEnum::COMPLETED->value,
                'type'                    => PaymentTypeEnum::REFUND,
                'original_payment_id'     => $payment->id,
                'payment_account_shop_id' => $payment->payment_account_shop_id,
                'source'                  => ['type' => $payment->method, 'scheme' => $payment->sub_method],
            ], fn ($value) => $value !== null));

            $this->update($payment, [
                'total_refund' => $payment->total_refund + $refundAmount,
                'with_refund'  => true
            ]);

            AttachPaymentToInvoice::make()->action($invoice, $refundPayment, []);
            if ($invoice->order) {
                AttachPaymentToOrder::make()->action($invoice->order, $refundPayment, []);
            }

            return $refundPayment;
        });
    }

    /**
     * Asks Checkout.com to send the money back to what the customer paid with; returns its refund action id.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function refundAtCheckout(Payment $payment, float $amount, Invoice $invoice, string $idempotencyKey): string
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
        $refundRequest->reference = $invoice->reference;

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
            'amount'     => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'lte:'.$this->payment->amount - $this->payment->total_refund],
            'invoice_id' => [
                'required',
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
