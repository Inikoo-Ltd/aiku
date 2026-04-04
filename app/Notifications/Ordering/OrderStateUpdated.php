<?php

namespace App\Notifications\Ordering;

use App\Models\Ordering\Order;
use App\Notifications\BaseSmartNotification;
use Illuminate\Bus\Queueable;
use App\Enums\Ordering\Order\OrderStateEnum;

class OrderStateUpdated extends BaseSmartNotification
{
    use Queueable;

    protected string $typeSlug = 'order.state_update';

    public function __construct(public Order $order)
    {
        //
    }

    public function toArray($notifiable): array
    {
        $stateLabel = OrderStateEnum::labels()[$this->order->state->value] ?? $this->order->state->value;
        $title = "Order {$this->order->reference} Updated";
        $body = "Order #{$this->order->reference} status updated to {$stateLabel}.";

        if ($this->order->is_premium_dispatch) {
            $title = "Order Priority {$this->order->reference} Updated";
            $body = "Order priority #{$this->order->reference} status updated to {$stateLabel}";
        }
        return [
            'title' => $title,
            'body'  => $body,
            'type'  => class_basename($this->order),
            'slug'  => $this->order->slug,
            'id'    => $this->order->id,
            'route' => route('grp.org.shops.show.ordering.orders.show', [
                'organisation' => $this->order->organisation->slug,
                'shop'         => $this->order->shop->slug,
                'order'        => $this->order->slug
            ]),
        ];
    }
}
