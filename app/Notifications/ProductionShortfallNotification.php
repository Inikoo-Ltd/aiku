<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\Ordering\Order;
use App\Models\Production\Production;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductionShortfallNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    /**
     * @param  array<int, string>  $lines  one per SKO to make, e.g. "ArtTT-12 50g Organic Biodigest Rooibos: 1"
     */
    public function __construct(public Production $production, public Order $order, public array $lines)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        return __('Order :order needs products made: :count on the To Produce board', ['order' => $this->order->reference, 'count' => count($this->lines)]);
    }

    public function body(): string
    {
        return implode("\n", $this->lines);
    }

    public function url(): string
    {
        return route($this->route()['name'], $this->route()['parameters']);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => 'production_shortfall',
            'slug'  => $this->order->slug,
            'route' => $this->route(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = $this->newMailMessage()->subject($this->title())
            ->line(__('There is not enough stock for this order. These SKOs are waiting on the To Produce board for a job order:'));
        foreach ($this->lines as $line) {
            $message->line($line);
        }

        return $message
            ->action(__('Open To Produce'), $this->url())
            ->line(__('You get this email because you are a production floor supervisor.'));
    }

    private function route(): array
    {
        return [
            'name'       => 'grp.org.productions.show.to_produce.index',
            'parameters' => [
                'organisation' => $this->production->organisation->slug,
                'production'   => $this->production->slug,
            ],
        ];
    }
}
