<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class StockDeliveryDispatchedByAgentNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    /**
     * @param Collection<int, PurchaseOrder> $purchaseOrders the buyer's own orders in the container
     */
    public function __construct(public StockDelivery $stockDelivery, public Collection $purchaseOrders, public string $agentUserName)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        return trans_choice(':delivery shipped with :count of your orders|:delivery shipped with :count of your orders', $this->purchaseOrders->count(), [
            'delivery' => $this->stockDelivery->reference,
            'count'    => $this->purchaseOrders->count(),
        ]);
    }

    public function body(): string
    {
        return __(':user, from the agent, marked it as shipped: :orders', [
            'user'   => $this->agentUserName,
            'orders' => $this->purchaseOrders->pluck('reference')->implode(', '),
        ]);
    }

    public function url(): string
    {
        return route('grp.org.procurement.stock_deliveries.show', [$this->stockDelivery->organisation->slug, $this->stockDelivery->slug]);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => 'stock_delivery_dispatched_by_agent',
            'slug'  => (string) $this->stockDelivery->id,
            'route' => [
                'name'       => 'grp.org.procurement.stock_deliveries.show',
                'parameters' => [$this->stockDelivery->organisation->slug, $this->stockDelivery->slug],
            ],
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = $this->newMailMessage()
            ->subject($this->title())
            ->line(__(':user, from the agent, marked :delivery as shipped. Your orders in it:', [
                'user'     => $this->agentUserName,
                'delivery' => $this->stockDelivery->reference,
            ]));

        foreach ($this->purchaseOrders as $purchaseOrder) {
            $url = route('grp.org.procurement.purchase_orders.show', [$this->stockDelivery->organisation->slug, $purchaseOrder->slug]);
            $message->line("[$purchaseOrder->reference]($url)");
        }

        return $message
            ->action(__('Open the delivery'), $this->url())
            ->line(__('You get this email because you are the buyer of these orders.'));
    }
}
