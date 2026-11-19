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
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierDepositRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsMailThroughSes;

    public bool $byEmail = true;

    public function __construct(public PurchaseOrder $purchaseOrder, public string $title, public string $body, public string $whyLine, public array $route)
    {
        $this->locale = app()->getLocale();
        $this->afterCommit();
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
            ->line($this->plainLine($this->body))
            ->action(__('Open the order'), route($this->route['name'], $this->route['parameters']))
            ->line($this->plainLine($this->whyLine));
    }
}
