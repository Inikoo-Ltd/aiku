<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-09 11:34
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Notifications;

use App\Models\SupplyChain\DepositRequest;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MoneyRequestApprovedNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    /**
     * @param  array{name: string, parameters: array<int|string, mixed>}  $route
     */
    public function __construct(
        public DepositRequest $depositRequest,
        public string $approverName,
        public string $amount,
        public bool $isRequester,
        public array $route
    ) {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        return __(':client approved money request :reference', [
            'client'    => $this->depositRequest->organisation->code,
            'reference' => $this->depositRequest->reference ?? '#'.$this->depositRequest->id,
        ]);
    }

    public function body(): string
    {
        return __(':user approved :amount. The money will be sent next; you will get another email when it is paid.', [
            'user'   => $this->approverName,
            'amount' => $this->amount,
        ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => 'money_request_approved',
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
            ->line($this->isRequester
                ? __('You get this email because you asked for this money.')
                : __('You get this email because you are copied on the money requests of this agent.'));
    }
}
