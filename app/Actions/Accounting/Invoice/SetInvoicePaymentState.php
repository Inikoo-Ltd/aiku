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

        /** @var Payment $payment */
        foreach (
            $invoice->payments()->where('payments.status', PaymentStatusEnum::SUCCESS)->get() as $payment
        ) {
            $runningPaymentsAmount += $payment->amount;
            if ($payStatus == InvoicePayStatusEnum::UNPAID && abs($runningPaymentsAmount) >= abs($invoice->total_amount)) {
                $payStatus = InvoicePayStatusEnum::PAID;
                $paymentAt = $payment->date;
            }
        }


        if (!$invoice->invoice_id) {
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
