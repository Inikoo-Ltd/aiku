<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-17 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Notifications;

use App\Models\Procurement\PurchaseOrder;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierDepositRequestedNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    public function __construct(public PurchaseOrder $purchaseOrder, public string $title, public string $body, public string $whyLine, public array $route)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'body'  => $this->body,
            'type'  => 'supplier_deposit_requested',
            'slug'  => (string) $this->purchaseOrder->id,
            'route' => $this->route,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject($this->title)
            ->line($this->body)
            ->action(__('Open the order'), route($this->route['name'], $this->route['parameters']))
            ->line($this->whyLine);
    }
}
