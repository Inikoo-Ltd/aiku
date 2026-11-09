<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:09
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Notifications;

use App\Models\SupplyChain\DepositRequest;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MoneyRequestCreatedNotification extends Notification
{
    use SendsMailThroughSes;

    public const string FOR_PAYER = 'payer';
    public const string FOR_COPY = 'copy';
    public const string FOR_REQUESTER = 'requester';

    public bool $byEmail = true;

    /**
     * @param  list<string>  $lines
     * @param  array{name: string, parameters: array<int|string, mixed>}  $route
     */
    public function __construct(
        public DepositRequest $depositRequest,
        public string $requesterName,
        public string $amount,
        public array $lines,
        public string $recipientRole,
        public array $route,
        public bool $isReminder = false
    ) {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        $parameters = [
            'agent'     => $this->depositRequest->agent->code,
            'client'    => $this->depositRequest->organisation->code,
            'amount'    => $this->amount,
            'reference' => $this->reference(),
        ];

        return $this->isReminder
            ? __('Reminder: :agent still asks :client for :amount (money request :reference)', $parameters)
            : __(':agent asks :client for :amount (money request :reference)', $parameters);
    }

    public function body(): string
    {
        return $this->recipientRole === self::FOR_PAYER
            ? __(':user, from the agent, asked for it. Approve the request, then pay it with the bank statement of the transfer.', ['user' => $this->requesterName])
            : __(':user, from the agent, asked for it.', ['user' => $this->requesterName]);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => $this->isReminder ? 'money_request_reminder' : 'money_request_created',
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

        foreach ($this->lines as $line) {
            $message->line('- '.$line);
        }

        if ($this->depositRequest->notes) {
            $message->line(__('Note from the agent: :notes', ['notes' => $this->depositRequest->notes]));
        }

        return $message
            ->action($this->recipientRole === self::FOR_PAYER ? __('Approve and pay') : __('Open the money request'), route($this->route['name'], $this->route['parameters']))
            ->line(match ($this->recipientRole) {
                self::FOR_PAYER     => __('You get this email because you approve and pay the money requests of this agent.'),
                self::FOR_REQUESTER => __('You get this email because you asked for this money.'),
                default             => __('You get this email because you are copied on the money requests of this agent.'),
            });
    }

    private function reference(): string
    {
        return $this->depositRequest->reference ?? '#'.$this->depositRequest->id;
    }
}
