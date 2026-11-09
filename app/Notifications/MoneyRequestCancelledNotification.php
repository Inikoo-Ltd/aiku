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

class MoneyRequestCancelledNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    /**
     * @param  array{name: string, parameters: array<int|string, mixed>}  $route
     */
    public function __construct(
        public DepositRequest $depositRequest,
        public string $cancellerName,
        public string $amount,
        public string $role,
        public array $route
    ) {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        return __(':agent withdrew money request :reference', [
            'agent'     => $this->depositRequest->agent->code,
            'reference' => $this->depositRequest->reference ?? '#'.$this->depositRequest->id,
        ]);
    }

    public function body(): string
    {
        return __(':user withdrew the request for :amount. Nothing needs to be approved or paid for it any more.', [
            'user'   => $this->cancellerName,
            'amount' => $this->amount,
        ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => 'money_request_cancelled',
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
            ->line(match ($this->role) {
                MoneyRequestCreatedNotification::FOR_PAYER     => __('You get this email because you approve and pay the money requests of this agent.'),
                MoneyRequestCreatedNotification::FOR_REQUESTER => __('You get this email because you asked for this money.'),
                default                                        => __('You get this email because you are copied on the money requests of this agent.'),
            });
    }
}
