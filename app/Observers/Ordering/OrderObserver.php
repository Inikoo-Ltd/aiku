<?php

namespace App\Observers\Ordering;

use App\Models\Ordering\Order;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Events\BroadcastUserNotification;
use App\Notifications\Ordering\OrderStateUpdated;
use App\Services\Notification\NotificationRecipientResolver;
use Illuminate\Support\Facades\Notification;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if ($order->isDirty('state')) {
            // if (! $order->is_premium_dispatch) {
            //     return;
            // }
            $resolver = app(NotificationRecipientResolver::class);
            $users = $resolver->resolveForOrder($order, 'order.state_update');

            Notification::send($users, new OrderStateUpdated($order));

            // Broadcast event for realtime toast
            $stateLabel = OrderStateEnum::labels()[$order->state->value] ?? $order->state->value;
            $title = "Order {$order->reference} Updated";
            $body = "Order #{$order->reference} status updated to {$stateLabel}.";

            if ($order->is_premium_dispatch) {
                $title = "Order Priority {$order->reference} Updated";
                $body = "Order priority #{$order->reference} status updated to {$stateLabel}";
            }
            BroadcastUserNotification::dispatch(
                $order->group,
                $order,
                $title,
                $body
            );
        }
    }
}
