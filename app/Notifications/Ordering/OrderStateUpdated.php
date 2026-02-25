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

        return [
            'order_id' => $this->order->id,
            'reference' => $this->order->reference,
            'state' => $this->order->state->value,
            'message' => "Order #{$this->order->reference} status updated to {$stateLabel}",
        ];
    }
}
