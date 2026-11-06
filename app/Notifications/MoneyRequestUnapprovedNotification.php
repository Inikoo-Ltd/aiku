<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 15:02
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Notifications;

use App\Models\SupplyChain\DepositRequest;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MoneyRequestUnapprovedNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    /**
     * @param  array{name: string, parameters: array<int|string, mixed>}  $route
     */
    public function __construct(public DepositRequest $depositRequest, public string $unapproverName, public array $route)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        return __(':client cancelled its approval of money request :reference', [
            'client'    => $this->depositRequest->organisation->code,
            'reference' => $this->depositRequest->reference ?? '#'.$this->depositRequest->id,
        ]);
    }

    public function body(): string
    {
        return __(':user cancelled the approval. The request is open again and waits for a new approval before it can be paid.', ['user' => $this->unapproverName]);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => 'money_request_unapproved',
            'slug'  => (string) $this->depositRequest->id,
            'route' => $this->route,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject($this->title())
            ->line($this->title())
            ->line($this->body())
            ->action(__('Open the money requests'), route($this->route['name'], $this->route['parameters']))
            ->line(__('You get this email because you asked for this money.'));
    }
}
