<?php

/*
 * author Arya Permana - Kirin
 * created on 18-03-2025-15h-13m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\OrgAction;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class RefundToPaymentAccount extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Invoice $invoice, PaymentAccount $paymentAccount, array $modelData): Payment
    {
        // Imagine the following scenario:
        // invoice = 100
        // pay in = 100
        // refund = -50
        // pay out = [-25,-25] (2 refund transactions)
        // input amount = -35

        if (!$invoice->invoice_id) {
            $refunds = $invoice->refunds->where('in_process', false)->all();
            $totalRefund = $invoice->refunds->where('in_process', false)->sum('total_amount'); // -50
            $totalToPay = -abs(Arr::get($modelData, 'amount')); // -35

            $payment = null;
            foreach ($refunds as $refund) {
                if ($totalRefund >= 0 || $totalToPay >= 0) {
                    break;
                }

                // Ensure we pay only the minimum required amount per refund
                $amountPayPerRefund = max($totalToPay, $refund->total_amount); // -25

                $payment = StorePayment::make()->action($invoice->customer, $paymentAccount, [
                    'amount' => $amountPayPerRefund,
                    'status' => PaymentStatusEnum::SUCCESS->value,
                    'state' => PaymentStateEnum::COMPLETED->value,
                ]);
                AttachPaymentToInvoice::make()->action($refund, $payment, []);

                $totalRefund -= $amountPayPerRefund; // -50 become -25
                $totalToPay -= $amountPayPerRefund; // -35 become -10
            }

            return $payment ?? null;
        }


        $payment = StorePayment::make()->action($invoice->customer, $paymentAccount, [
            'amount' => -abs(Arr::get($modelData, 'amount')),
            'status' => PaymentStatusEnum::SUCCESS->value,
            'state' => PaymentStateEnum::COMPLETED->value,
        ]);

        AttachPaymentToInvoice::make()->action($invoice, $payment, []);

        return $payment;
    }

    public function rules(): array
    {
        return [
            'amount'     => ['required', 'numeric'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Invoice $refund, PaymentAccount $paymentAccount, array $modelData): Payment
    {
        $this->initialisationFromShop($refund->shop, $modelData);

        return $this->handle($refund, $paymentAccount, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Invoice $refund, PaymentAccount $paymentAccount, ActionRequest $request): void
    {
        $this->initialisationFromShop($refund->shop, $request);

        $this->handle($refund, $paymentAccount, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
