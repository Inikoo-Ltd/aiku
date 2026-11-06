<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:48
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Notifications;

use App\Models\SupplyChain\DepositRequest;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MoneyRequestRejectedNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    /**
     * @param  array{name: string, parameters: array<int|string, mixed>}  $route
     */
    public function __construct(public DepositRequest $depositRequest, public string $rejecterName, public bool $isRequester, public array $route)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        return __(':client rejected money request :reference', [
            'client'    => $this->depositRequest->organisation->code,
            'reference' => $this->depositRequest->reference ?? '#'.$this->depositRequest->id,
        ]);
    }

    public function body(): string
    {
        return __(':user rejected it: :reason', ['user' => $this->rejecterName, 'reason' => $this->depositRequest->rejection_reason]);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => 'money_request_rejected',
            'slug'  => (string) $this->depositRequest->id,
            'route' => $this->route,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = $this->newMailMessage()
            ->subject($this->title())
            ->line($this->title())
            ->line($this->body());

        if ($this->isRequester) {
            $message->line(__('Nothing more will be paid against this request. You can ask for the money again with a new request.'));
        }

        return $message
            ->action(__('Open the money requests'), route($this->route['name'], $this->route['parameters']))
            ->line($this->isRequester
                ? __('You get this email because you asked for this money.')
                : __('You get this email because you are copied on the money requests of this agent.'));
    }
}
