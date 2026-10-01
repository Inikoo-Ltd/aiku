<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttachPaymentToInvoice extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Invoice $invoice, Payment $payment, array $modelData): void
    {
        DB::transaction(function () use ($invoice, $payment) {
            if ((float) $payment->amount < 0) {
                self::lockRefundToPay($invoice, (float) $payment->amount);
            }

            $invoice->payments()->attach($payment);

            UpdateInvoicePaymentState::run($invoice);
        });
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function lockRefundToPay(Invoice $invoice, float $amount): void
    {
        if ($invoice->type !== InvoiceTypeEnum::REFUND) {
            return;
        }

        if ($invoice->order_id) {
            Order::lockForUpdate()->findOrFail($invoice->order_id);
        }

        $refund = Invoice::lockForUpdate()->findOrFail($invoice->id);
        if (round(abs($amount), 2) > round(abs((float) $refund->total_amount) - abs((float) $refund->payment_amount), 2)) {
            throw ValidationException::withMessages(['amount' => __('The amount is more than is left to pay on this refund')]);
        }
    }

    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'numeric'],
        ];
    }

    public function action(Invoice $invoice, Payment $payment, array $modelData): void
    {
        $this->asAction = true;
        $this->initialisationFromShop($invoice->shop, $modelData);
        $this->handle($invoice, $payment, $modelData);
    }
}
