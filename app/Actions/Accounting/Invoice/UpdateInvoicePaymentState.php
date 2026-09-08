<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Feb 2025 19:54:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\OrgAction;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Accounting\Invoice\InvoicePayDetailedStatusEnum;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class UpdateInvoicePaymentState extends OrgAction
{
    use WithHydrateCommand;

    public string $commandSignature = 'invoices:update_payment_state {organisations?*} {--S|shop= shop slug} {--s|slug=} {--ids= : Comma separated invoice ids}';


    public function __construct()
    {
        $this->model = Invoice::class;
    }

    protected function handle(Invoice $invoice): Invoice
    {
        $payStatus             = InvoicePayStatusEnum::UNPAID;
        $payDetailedStatus     = InvoicePayDetailedStatusEnum::UNPAID;
        $paymentAt             = null;
        $runningPaymentsAmount = 0;


        $payments = $invoice->payments()
            ->where('payments.status', PaymentStatusEnum::SUCCESS)
            ->whereNot('payments.state', PaymentStateEnum::CANCELLED)
            ->orderBy('payments.date')
            ->get();

        /** @var Payment $payment */
        foreach (
            $payments as $payment
        ) {
            $runningPaymentsAmount += $payment->amount;

            // Rounded before comparing: the running total is an unrounded float, so two
            // payments of 186.36 against a 372.72 invoice sum to 372.71999999999997 and
            // the invoice settles without ever taking a payment date.
            if (abs(round($runningPaymentsAmount, 2)) >= abs($invoice->total_amount) && $paymentAt === null) {
                $paymentAt = $payment->date;
            }
        }

        $runningPaymentsAmount = round($runningPaymentsAmount, 2);
        if ($invoice->type == InvoiceTypeEnum::INVOICE) {

            if ($runningPaymentsAmount > $invoice->total_amount) {
                $payDetailedStatus = InvoicePayDetailedStatusEnum::OVERPAID;
                $payStatus = InvoicePayStatusEnum::PAID;
            } elseif ($runningPaymentsAmount == $invoice->total_amount) {
                $payDetailedStatus = InvoicePayDetailedStatusEnum::PAID;
                $payStatus = InvoicePayStatusEnum::PAID;
            } elseif ($runningPaymentsAmount > 0) {
                $payDetailedStatus = InvoicePayDetailedStatusEnum::PARTIALLY_PAID;

            }
        } else {

            if ($runningPaymentsAmount < $invoice->total_amount) {
                $payDetailedStatus = InvoicePayDetailedStatusEnum::OVERPAID;
                $payStatus = InvoicePayStatusEnum::PAID;
            } elseif ($runningPaymentsAmount == $invoice->total_amount) {
                $payDetailedStatus = InvoicePayDetailedStatusEnum::PAID;
                $payStatus = InvoicePayStatusEnum::PAID;
            } elseif ($runningPaymentsAmount < 0) {
                $payDetailedStatus = InvoicePayDetailedStatusEnum::PARTIALLY_PAID;
            }
        }



        $cutOffDate = Arr::get($invoice->shop->settings, 'unpaid_invoices_unknown_before', config('app.unpaid_invoices_unknown_before'));
        if ($cutOffDate) {
            $cutOffDate = Carbon::parse($cutOffDate);
        }

        if ($runningPaymentsAmount == 0 && $cutOffDate && $invoice->created_at->lt($cutOffDate)) {
            $payStatus         = InvoicePayStatusEnum::UNKNOWN;
            $payDetailedStatus = InvoicePayDetailedStatusEnum::UNKNOWN;
        }


        $invoice->update(
            [
                'pay_status'          => $payStatus,
                'paid_at'             => $paymentAt,
                'pay_detailed_status' => $payDetailedStatus,
                'payment_amount'      => $runningPaymentsAmount
            ]
        );

        return $invoice;
    }

}
