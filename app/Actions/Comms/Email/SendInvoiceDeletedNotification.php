<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
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

class SendInvoiceDeletedNotification extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;
    use WithSendSubscribersOutboxEmail;


    public function handle(Invoice $invoice): void
    {
        /** @var Outbox $outbox */
        $outbox = $invoice->shop->outboxes()->where('code', OutboxCodeEnum::INVOICE_DELETED->value)->first();

        $customer = $invoice->customer;

        $this->sendOutboxEmailToSubscribers(
            $outbox,
            additionalData: [
                'customer_name' => $customer->name,
                'invoice_reference' => $invoice->reference,
                'date' => $invoice->deleted_at->format('F jS, Y'),
                'invoice_link' => route('grp.org.accounting.deleted_invoices.show', [
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
