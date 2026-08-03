<?php

/*
 * author eka yudinata
 * created on 02-07-2026
 * github: https://github.com/ekayudinata
 * copyright 2026
*/

namespace App\Actions\Comms\Email;

use App\Actions\OrgAction;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Comms\DispatchedEmail;
use App\Models\CRM\Customer;
use App\Actions\Comms\Traits\WithSendCustomerOutboxEmail;
use App\Actions\Accounting\Invoice\WithInvoicesExport;

class SendInvoicePaidEmailToCustomer extends OrgAction
{
    use WithSendCustomerOutboxEmail;
    use WithInvoicesExport;

    public string $jobQueue = 'low-priority';

    public function handle(Customer $customer, array $additionalData = []): DispatchedEmail|null
    {
        $attachments = [];

        $invoice = Invoice::find($additionalData['invoice_id'] ?? null);
        if ($invoice) {
            $attachments[] = [
                'content'  => $this->getInvoicePdfContent($invoice),
                'filename' => $invoice->slug.'.pdf',
            ];

            $additionalData['download_invoice_pdf_link'] = $invoice->shop->website->getUrl().'/app/invoices/'.$invoice->slug.'/pdf';
        }

        return $this->sendCustomerOutboxEmail($customer, OutboxCodeEnum::INVOICE_PAID, $additionalData, attachments: $attachments);
    }
}
