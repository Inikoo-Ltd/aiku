<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Invoice\AttachPaymentToInvoice;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\OrgAction;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\PaymentAccount;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Comms\Outbox\ProcessInvoicePaidNotification;
use Sentry;
use App\Actions\Traits\Authorisations\Ordering\WithOrderPaymentAuthorisation;

class PayOrder extends OrgAction
{
    use WithOrderPaymentAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order, PaymentAccount $paymentAccount, array $modelData): Payment
    {

        [$payment, $invoice] = DB::transaction(function () use ($order, $paymentAccount, $modelData) {
            $payment = StorePayment::make()->action($order->customer, $paymentAccount, $modelData);

            if ($paymentAccount->is_accounts) {
                StoreCreditTransaction::make()->action($order->customer, [
                    'amount'     => -$payment->amount,
                    'type'       => CreditTransactionTypeEnum::PAYMENT,
                    'payment_id' => $payment->id,
                ]);
            }

            AttachPaymentToOrder::make()->action($order, $payment, []);

            $invoice = $order->invoices()->where('invoices.type', InvoiceTypeEnum::INVOICE)->first();
            if ($invoice) {
                AttachPaymentToInvoice::make()->action($invoice, $payment, []);
            }

            return [$payment, $invoice];
        });

        if ($invoice) {
            ProcessInvoicePaidNotification::dispatch($invoice->id);
        }

        $this->sendPaidOrderToWarehouse($order);

        return $payment;
    }

    private function sendPaidOrderToWarehouse(Order $order): void
    {
        $order->refresh();

        if ($order->state != OrderStateEnum::SUBMITTED || $order->pay_status != OrderPayStatusEnum::PAID) {
            return;
        }

        try {
            SendOrderToWarehouse::make()->action($order, []);
        } catch (\Throwable $e) {
            Sentry::captureException($e);
        }
    }

    public function rules(): array
    {
        return [
            'amount'       => ['required', 'decimal:0,2'],
            'reference'    => ['nullable', 'string', 'max:255'],
            'status'       => ['sometimes', 'required', Rule::enum(PaymentStatusEnum::class)],
            'state'        => ['sometimes', 'required', Rule::enum(PaymentStateEnum::class)],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order, PaymentAccount $paymentAccount, array $modelData): Payment
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $paymentAccount, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, PaymentAccount $paymentAccount, ActionRequest $request): Payment
    {
        $this->initialisationFromShop($order->shop, $request);
        $this->refuseBankPaymentRecordedAutomatically($order, (float)$this->validatedData['amount']);

        return $this->handle($order, $paymentAccount, $this->validatedData);
    }

    /**
     * A person recording by hand money the bank sync already recorded on this order (HELP-4642): once a bank payment is
     * on the order, a manual payment that would take it past what is still owed is the same money again. Part payments,
     * the rest of a deposit and corrections (zero or negative) go through.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function refuseBankPaymentRecordedAutomatically(Order $order, float $amount): void
    {
        $owed = $order->amountStillToPay();
        if ($amount <= 0 || $amount <= $owed + 0.01) {
            return;
        }

        $bankTransaction = BankTransaction::where('order_id', $order->id)
            ->whereNotNull('payment_id')
            ->whereHas('payment', fn ($query) => $query->where('status', PaymentStatusEnum::SUCCESS)->whereNot('state', PaymentStateEnum::CANCELLED))
            ->with('payment')
            ->latest('booked_at')
            ->first();
        if (!$bankTransaction) {
            return;
        }

        throw ValidationException::withMessages([
            'amount' => __('This order was already paid automatically from the bank on :date (:amount), and :owed is still owed. If the customer really paid twice, record the second payment from Accounting → Bank review.', [
                'date'   => $bankTransaction->booked_at->toDateString(),
                'amount' => number_format((float)$bankTransaction->payment->amount, 2).' '.$bankTransaction->currency->code,
                'owed'   => number_format(max($owed, 0), 2),
            ]),
        ]);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
