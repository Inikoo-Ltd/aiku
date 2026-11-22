<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\InvoiceEInvoiceTransmission;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EInvoiceProblemNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    public function __construct(public InvoiceEInvoiceTransmission $transmission)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => 'e_invoice_problem',
            'slug'  => 'e-invoice-'.$this->transmission->id,
            'route' => $this->url(),
        ];
    }

    public function title(): string
    {
        $reference = $this->transmission->invoice->reference;

        return match ($this->transmission->status) {
            InvoiceEInvoiceTransmission::NOT_BUILT => __('E-invoice :reference was not sent: it needs fixing', ['reference' => $reference]),
            'REJECTED'                             => __('The customer refused e-invoice :reference', ['reference' => $reference]),
            'INVALID'                              => __('E-invoice :reference was refused as invalid', ['reference' => $reference]),
            'FAILED'                               => __('E-invoice :reference could not be delivered', ['reference' => $reference]),
            default                                => __('E-invoice :reference is not delivered yet', ['reference' => $reference]),
        };
    }

    public function body(): string
    {
        return $this->transmission->errorMessage() ?: __('Status: :status', ['status' => $this->transmission->status]);
    }

    public function url(): string
    {
        $invoice = $this->transmission->invoice;

        return $invoice->type === InvoiceTypeEnum::REFUND
            ? route('grp.org.accounting.refunds.show', [$invoice->organisation->slug, $invoice->slug])
            : route('grp.org.accounting.invoices.show', [$invoice->organisation->slug, $invoice->slug]);
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject($this->title())
            ->line($this->plainLine($this->body()))
            ->action(__('Open the invoice'), $this->url());
    }
}
