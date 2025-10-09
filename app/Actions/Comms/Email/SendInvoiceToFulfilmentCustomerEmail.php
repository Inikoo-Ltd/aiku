<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 13:05:43 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithOrderingCustomerNotification;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Email;
use Lorisleiva\Actions\ActionRequest;

class SendInvoiceToFulfilmentCustomerEmail extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;
    use WithOrderingCustomerNotification;

    private Email $email;

    public function handle(Invoice $invoice): ?DispatchedEmail
    {
        if ($invoice->shop->type != ShopTypeEnum::FULFILMENT) {
            return null;
        }

        list($emailHtmlBody, $dispatchedEmail) = $this->getEmailBody($invoice->customer, OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER);
        if (!$emailHtmlBody) {
            return null;
        }
        $outbox = $dispatchedEmail->outbox;


        return $this->sendEmailWithMergeTags(
            $dispatchedEmail,
            $outbox->emailOngoingRun->sender(),
            $outbox->emailOngoingRun?->email?->subject,
            $emailHtmlBody,
            invoiceUrl: $this->getInvoiceLink($invoice),
        );
    }

    public function asController(Invoice $invoice, ActionRequest $request): ?DispatchedEmail
    {
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice);
    }
}
