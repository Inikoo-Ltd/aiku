<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Apr 2025 21:39:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Models\Accounting\Invoice;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsObject;
use OwenIt\Auditing\Events\AuditCustom;

class StoreDeletedInvoiceHistory
{
    use asObject;

    public function handle(Invoice $invoice): void
    {
        $customer = $invoice->customer;

        $customer->auditEvent     = 'delete';
        $customer->isCustomEvent  = true;
        $customer->auditCustomOld = [
            'return' => $invoice->reference
        ];
        $customer->auditCustomNew = [
            'invoice' =>
                $invoice->in_process
                    ?
                    __("Invoice still in process :ref has been deleted.", ['ref' => $invoice->reference])
                    :
                    '⚠️ '.__("The invoice :ref has been deleted.", ['ref' => $invoice->reference])
        ];
        Event::dispatch(AuditCustom::class, [
            $customer
        ]);
    }

}
