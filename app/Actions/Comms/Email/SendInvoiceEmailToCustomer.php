<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 13:05:43 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Email;
use App\Models\Comms\Outbox;
use Lorisleiva\Actions\ActionRequest;

class SendInvoiceEmailToCustomer extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;

    private Email $email;

    public function handle(Invoice $invoice): ?DispatchedEmail
    {
        // Todo remove this
        if ($invoice->shop->type != ShopTypeEnum::FULFILMENT) {
            return null;
        }

        $customer = $invoice->customer;
        $recipient       = $customer;
        if (!$recipient->email) {
            return null;
        }

        /** @var Outbox $outbox */
        $outbox = $customer->shop->outboxes()->where('code', OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER->value)->first();
        $outboxDispatch = $customer->shop->outboxes()->where('type', OutboxTypeEnum::MARKETING_NOTIFICATION)->first();


        $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $recipient, [
            'is_test'       => false,
            'outbox_id'     => $outboxDispatch->id,
            'email_address' => $recipient->email,
            'provider'      => DispatchedEmailProviderEnum::SES
        ]);
        $dispatchedEmail->refresh();

        $emailHtmlBody = $outbox->emailOngoingRun->email->liveSnapshot->compiled_layout;
        if (!$emailHtmlBody) {
            return null;
        }

        $baseUrl = 'https://fulfilment.test';
        if (app()->isProduction()) {
            $baseUrl = 'https://'.$invoice->shop->website->domain;
        }

        $invoiceUrl = $baseUrl.'/app/billing/invoices/'.$invoice->slug; // todo give correct link for B2B and DS shops
        if ($invoice->shop->type == ShopTypeEnum::FULFILMENT) {
            $invoiceUrl = $baseUrl.'/app/fulfilment/billing/invoices/'.$invoice->slug;
        }


        return $this->sendEmailWithMergeTags(
            $dispatchedEmail,
            $outbox->emailOngoingRun->sender(),
            $outbox->emailOngoingRun?->email?->subject,
            $emailHtmlBody,
            invoiceUrl: $baseUrl.'/app/login?ref='.$invoiceUrl
        );
    }

    public function asController(Invoice $invoice, ActionRequest $request): DispatchedEmail
    {
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice);
    }
}
