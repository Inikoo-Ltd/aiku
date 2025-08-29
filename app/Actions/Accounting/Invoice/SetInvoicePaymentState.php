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
        $payDetailedStatus     = InvoicePayDetailedStatusEnum::UNPAID;
        $paymentAt             = null;
        $runningPaymentsAmount = 0;


        $payments = $invoice->payments()
            ->where('payments.status', PaymentStatusEnum::SUCCESS)
            ->where('payments.type', '=', PaymentTypeEnum::PAYMENT)
            ->orderBy('payments.date')
            ->get();

        /** @var Payment $payment */
        foreach (
            $payments as $payment
        ) {
            $runningPaymentsAmount += $payment->amount;
            if (abs($runningPaymentsAmount) >= abs($invoice->total_amount) && $paymentAt === null) {
                $paymentAt = $payment->date;
            }
        }

        if($invoice->type==InvoiceTypeEnum::INVOICE){
            if ($runningPaymentsAmount > $invoice->total_amount) {
                $payDetailedStatus = InvoicePayDetailedStatusEnum::OVERPAID;
            } elseif ($runningPaymentsAmount == $invoice->total_amount) {
                $payDetailedStatus = InvoicePayDetailedStatusEnum::PAID;
            } elseif ($runningPaymentsAmount > 0) {
                $payDetailedStatus = InvoicePayDetailedStatusEnum::PARTIALLY_PAID;
            }
        }else{
            if ($runningPaymentsAmount < $invoice->total_amount) {
                $payDetailedStatus = InvoicePayDetailedStatusEnum::OVERPAID;
            } elseif ($runningPaymentsAmount == $invoice->total_amount) {
                $payDetailedStatus = InvoicePayDetailedStatusEnum::PAID;
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
