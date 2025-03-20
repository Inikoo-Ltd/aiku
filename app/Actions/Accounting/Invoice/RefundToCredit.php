<?php

/*
 * author Arya Permana - Kirin
 * created on 18-03-2025-15h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateCreditTransactions;
use App\Actions\OrgAction;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Models\Accounting\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class RefundToCredit extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Invoice $invoice, array $modelData): Invoice
    {

        $totalToPay = -abs(Arr::get($modelData, 'amount'));


        if (!$invoice->invoice_id) {
            $refunds = $invoice->refunds->where('in_process', false)->all();
            $totalRefund = $invoice->refunds->where('in_process', false)->sum('total_amount');

            foreach ($refunds as $refund) {
                if ($totalRefund >= 0 || $totalToPay >= 0) {
                    break;
                }

                $amountPayPerRefund = max($totalToPay, $refund->total_amount);

                $creditTransaction = StoreCreditTransaction::make()->action($refund->customer, [
                    'amount' => abs($amountPayPerRefund),
                    'date'  => now(),
                    'type' => CreditTransactionTypeEnum::MONEY_BACK
                ]);

                $creditTransaction->refresh();

                $payStatus             = InvoicePayStatusEnum::UNPAID;
                $paymentAt             = null;

                if ($payStatus == InvoicePayStatusEnum::UNPAID && $amountPayPerRefund >= $refund->total_amount) {
                    $payStatus = InvoicePayStatusEnum::PAID;
                    $paymentAt = $creditTransaction->date;
                }

                $refund->update([
                    'pay_status' => $payStatus,
                    'paid_at' => $paymentAt,
                    'payment_amount' => $amountPayPerRefund
                ]);

                $totalRefund -= $amountPayPerRefund;
                $totalToPay -= $amountPayPerRefund;
            }

            CustomerHydrateCreditTransactions::dispatch($invoice->customer);

            return $invoice;
        }

        $refund = $invoice;

        $creditTransaction = StoreCreditTransaction::make()->action($invoice->customer, [
            'amount' => $totalToPay,
            'date'  => now(),
            'type' => CreditTransactionTypeEnum::MONEY_BACK
        ]);
        $creditTransaction->refresh();

        $payStatus             = InvoicePayStatusEnum::UNPAID;
        $paymentAt             = null;

        if ($payStatus == InvoicePayStatusEnum::UNPAID && $totalToPay >= $refund->total_amount) {
            $payStatus = InvoicePayStatusEnum::PAID;
            $paymentAt = $creditTransaction->date;
        }

        $refund->update([
            'pay_status' => $payStatus,
            'paid_at' => $paymentAt,
            'payment_amount' => $totalToPay
        ]);

        CustomerHydrateCreditTransactions::dispatch($invoice->customer);

        return $refund;
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
    public function action(Invoice $refund, array $modelData): Invoice
    {
        $this->initialisationFromShop($refund->shop, $modelData);

        return $this->handle($refund, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Invoice $refund, ActionRequest $request): void
    {
        $this->initialisationFromShop($refund->shop, $request);

        $this->handle($refund, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
