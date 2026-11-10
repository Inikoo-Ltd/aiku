<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:09
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Notifications;

use App\Models\SupplyChain\AgentPayment;
use App\Models\SupplyChain\DepositRequest;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MoneyRequestPaidNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    /**
     * @param  array{name: string, parameters: array<int|string, mixed>}  $route
     */
    public function __construct(
        public DepositRequest $depositRequest,
        public AgentPayment $transfer,
        public string $payerName,
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
        return __(':client sent :amount for money request :reference', [
            'client'    => $this->depositRequest->organisation->code,
            'amount'    => $this->amount,
            'reference' => $this->depositRequest->reference ?? '#'.$this->depositRequest->id,
        ]);
    }

    public function body(): string
    {
        return __(':user sent it on :date. It takes about :days days to arrive, so expect it around :arrival. The bank statement is on the ledger.', [
            'user'    => $this->payerName,
            'date'    => $this->transfer->date?->toFormattedDateString(),
            'days'    => AgentPayment::MONEY_REQUEST_TRANSFER_DAYS,
            'arrival' => $this->transfer->expectedArrival()?->toFormattedDateString(),
        ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => 'money_request_paid',
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
            ->action(__('Open the ledger'), route($this->route['name'], $this->route['parameters']))
            ->line($this->isRequester
                ? __('You get this email because you asked for this money.')
                : __('You get this email because you are copied on the money requests of this agent.'));
    }
}
