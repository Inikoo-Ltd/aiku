<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Notifications;

use App\Models\Procurement\OrgSupplier;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierOrderProposalNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsMailThroughSes;

    public bool $byEmail = true;

    /**
     * @param  array{name: string, parameters: list<string>}  $route
     */
    public function __construct(public OrgSupplier $orgSupplier, public string $title, public string $body, public string $whyLine, public array $route, public string $type = 'supplier_order_proposal')
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
            'type'  => $this->type,
            'slug'  => (string) $this->orgSupplier->id,
            'route' => $this->route,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject($this->title)
            ->line($this->plainLine($this->body))
            ->action(__('Review the proposal'), route($this->route['name'], $this->route['parameters']))
            ->line($this->plainLine($this->whyLine));
    }
}
