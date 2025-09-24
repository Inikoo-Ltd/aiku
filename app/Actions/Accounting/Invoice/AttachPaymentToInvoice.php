<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use Illuminate\Support\Arr;

class AttachPaymentToInvoice extends OrgAction
{
    public function handle(Invoice $invoice, Payment $payment, array $modelData): void
    {
        $amount = Arr::get($modelData, 'amount', $payment->amount);

        $invoice->payments()->attach($payment, [
            'amount' => $amount,
        ]);

        UpdateInvoicePaymentState::run($invoice);
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
