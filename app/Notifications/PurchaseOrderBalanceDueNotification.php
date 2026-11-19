<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Notifications\Concerns\SendsMailThroughSes;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PurchaseOrderBalanceDueNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    public function __construct(public PurchaseOrder $purchaseOrder, public string $balance, public ?string $remindedBy = null)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        if ($this->remindedBy !== null) {
            return __(':name reminds you about the balance of :amount on :reference', ['name' => $this->remindedBy, 'amount' => $this->balance, 'reference' => $this->purchaseOrder->reference]);
        }

        return __(':reference passed its QC: the balance of :amount can be paid', ['reference' => $this->purchaseOrder->reference, 'amount' => $this->balance]);
    }

    public function body(): string
    {
        return $this->remindedBy !== null
            ? __('Not paid yet? Pay the supplier, then record the payment on the order. Already paid? Upload the bank statement on the order so :name can see it.', ['name' => $this->remindedBy])
            : __('Record the payment on the order with its bank statement.');
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => 'purchase_order_balance_due',
            'slug'  => (string) $this->purchaseOrder->id,
            'route' => $this->route(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject($this->title())
            ->line($this->titleWithOrderLink())
            ->line($this->body())
            ->action(__('Open the order'), $this->orderUrl());
    }

    private function titleWithOrderLink(): HtmlString
    {
        $reference = e($this->purchaseOrder->reference);
        $link      = '<a href="'.e($this->orderUrl()).'" style="white-space: nowrap;">'.$reference.'</a>';
        $title     = e($this->title());
        $position  = strpos($title, $reference);

        return new HtmlString($position === false ? $title : substr_replace($title, $link, $position, strlen($reference)));
    }

    private function orderUrl(): string
    {
        return route($this->route()['name'], $this->route()['parameters']);
    }

    public function route(): array
    {
        return [
            'name'       => 'grp.org.procurement.purchase_orders.show',
            'parameters' => [$this->purchaseOrder->organisation->slug, $this->purchaseOrder->slug],
        ];
    }
}
