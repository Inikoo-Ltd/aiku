<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StuckPurchaseOrdersSummaryNotification extends Notification
{
    use SendsMailThroughSes;

    /**
     * @param array<int, array{reference: string, parent: ?string, organisation: ?string, waiting: ?string, days_overdue: int, buyer: ?string, url: string}> $orders
     */
    public function __construct(public array $orders, public bool $ownOrdersOnly)
    {
    }

    public bool $byEmail = true;

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => collect($this->orders)->take(5)->map(fn (array $order) => $order['reference'].' ('.$order['days_overdue'].'d)')->implode(', '),
            'type'  => 'purchase_orders_stuck',
            'slug'  => 'stuck-'.now()->toDateString(),
            'route' => ['name' => 'grp.supply-chain.dashboard', 'parameters' => ['status' => 'overdue']],
        ];
    }

    public function title(): string
    {
        return trans_choice(':count purchase order is past its expected date|:count purchase orders are past their expected date', count($this->orders), ['count' => count($this->orders)]);
    }

    public function toMail($notifiable): MailMessage
    {
        $message = $this->newMailMessage()
            ->subject($this->title())
            ->line($this->ownOrdersOnly
                ? __('Your purchase orders that are past the expected date of their current stage, most late first:')
                : __('Every purchase order past the expected date of its current stage, most late first:'));

        foreach ($this->orders as $order) {
            $message->line(__(':reference (:parent, :organisation): :waiting, :days days late:buyer', [
                'reference'    => "[{$order['reference']}]({$order['url']})",
                'parent'       => $order['parent'] ?? '-',
                'organisation' => $order['organisation'] ?? '-',
                'waiting'      => $order['waiting'] ?? '-',
                'days'         => $order['days_overdue'],
                'buyer'        => !$this->ownOrdersOnly && $order['buyer'] ? ' · '.$order['buyer'] : '',
            ]));
        }

        return $message
            ->action(__('Open the PO Overview'), route('grp.supply-chain.dashboard', ['status' => 'overdue']))
            ->line(__('Sent every Monday to the supply chain managers and to the buyer of each order.'));
    }
}
