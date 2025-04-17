<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Feb 2025 19:54:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\OrgAction;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class SetInvoicePaymentState extends OrgAction
{
    use WithHydrateCommand;

    public string $commandSignature = 'invoices:set_payment_state {organisations?*} {--S|shop= shop slug} {--s|slug=}';


    public function __construct()
    {
        $this->model = Invoice::class;
    }

    protected function handle(Invoice $invoice): Invoice
    {
        $payStatus             = InvoicePayStatusEnum::UNPAID;
        $paymentAt             = null;
        $runningPaymentsAmount = 0;

        if (!$invoice->original_invoice_id) {
            $payments = $invoice->payments()
                ->where('payments.status', PaymentStatusEnum::SUCCESS)
                ->where('payments.type', '=', PaymentTypeEnum::PAYMENT)
                ->get();
        } else {
            $payments = $invoice->payments()
                ->where('payments.status', PaymentStatusEnum::SUCCESS)
                ->where('payments.type', '=', PaymentTypeEnum::REFUND)
                ->get();
        }

        /** @var Payment $payment */
        foreach (
            $payments as $payment
        ) {
            $runningPaymentsAmount += $payment->amount;
            if ($payStatus == InvoicePayStatusEnum::UNPAID && abs($runningPaymentsAmount) >= abs($invoice->total_amount)) {
                $payStatus = InvoicePayStatusEnum::PAID;
                $paymentAt = $payment->date;
            }
        }


        if (!$invoice->original_invoice_id) {
            $cutOffDate = Arr::get($invoice->shop->settings, 'unpaid_invoices_unknown_before', config('app.unpaid_invoices_unknown_before'));
            if ($cutOffDate) {
                $cutOffDate = Carbon::parse($cutOffDate);
            }

            if ($payStatus == InvoicePayStatusEnum::UNPAID && $cutOffDate && $invoice->created_at->lt($cutOffDate)) {
                $payStatus = InvoicePayStatusEnum::UNKNOWN;
            }
        }

        $invoice->update(
            [
                'pay_status'     => $payStatus,
                'paid_at'        => $paymentAt,
                'payment_amount' => $runningPaymentsAmount
            ]
        );

        return $invoice;
    }

}
