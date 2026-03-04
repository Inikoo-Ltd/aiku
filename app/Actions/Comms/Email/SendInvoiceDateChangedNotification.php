<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 4 Mar 2026 09:46:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\Comms\Traits\WithSendSubscribersOutboxEmail;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Comms\Outbox;

// TODO: Update this section when testing
class SendInvoiceDateChangedNotification extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;
    use WithSendSubscribersOutboxEmail;


    public function handle(Invoice $invoice, string $previousDate, string $newDate): void
    {
        /** @var Outbox $outbox */
        $outbox = $invoice->shop->outboxes()->where('code', OutboxCodeEnum::INVOICE_DATE_CHANGED->value)->first();

        $customer = $invoice->customer;

        $this->sendOutboxEmailToSubscribers(
            $outbox,
            additionalData: [
                'customer_name' => $customer->name,
                'invoice_reference' => $invoice->reference,
                'previous_date' => $previousDate,
                'new_date' => $newDate,
                'invoice_link' => route('grp.org.accounting.invoices.show', [
                    $invoice->organisation->slug,
                    $invoice->slug
                ]),
                'customer_link' => $customer->shop->fulfilment
                    ? route('grp.org.fulfilments.show.crm.customers.show', [
                        $customer->organisation->slug,
                        $customer->shop->fulfilment->slug,
                        $customer->fulfilmentCustomer->slug
                    ])
                    : route('grp.org.shops.show.crm.customers.show', [
                        $customer->organisation->slug,
                        $customer->shop->slug,
                        $customer->slug
                    ]),
            ]
        );
    }


}
