<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\SupplyChain\DepositRequest;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MoneyRequestPaymentUndoneNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    /**
     * @param  array{name: string, parameters: array<int|string, mixed>}  $route
     */
    public function __construct(public DepositRequest $depositRequest, public string $undoerName, public string $amount, public bool $isRequester, public array $route)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        return __(':client undid a payment on money request :reference', [
            'client'    => $this->depositRequest->organisation->code,
            'reference' => $this->depositRequest->reference ?? '#'.$this->depositRequest->id,
        ]);
    }

    public function body(): string
    {
        return __(':user undid :amount that was set as paid from their bank. The request is open to pay again.', ['user' => $this->undoerName, 'amount' => $this->amount]);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => 'money_request_payment_undone',
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
            $message->line(__('Do not count this money as received until the client pays the request again.'));
        }

        return $message
            ->action(__('Open the money requests'), route($this->route['name'], $this->route['parameters']))
            ->line($this->isRequester
                ? __('You get this email because you asked for this money.')
                : __('You get this email because you are copied on the money requests of this agent.'));
    }
}
