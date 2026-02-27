<?php

namespace App\Observers\Ordering;

use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Events\BroadcastUserNotification;
use App\Notifications\Ordering\OrderStateUpdated;
use Illuminate\Support\Facades\Notification;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if ($order->isDirty('state')) {
            if (! $order->is_premium_dispatch) {
                return;
            }

            // Notify active staff users who are working employees
            $employeeUsers = User::where('status', true)
                ->whereHas('employees', function ($query) {
                    $query->where('employees.state', EmployeeStateEnum::WORKING);
                })
                ->get();

            $guestUsers = User::where('status', true)
                ->whereHas('guests', function ($query) {
                    $query->where('guests.status', true);
                })
                ->get();

            $users = $employeeUsers->merge($guestUsers)->unique('id');

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
