<?php

namespace App\Observers\Ordering;

use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Notifications\Ordering\OrderStateUpdated;
use Illuminate\Support\Facades\Notification;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if ($order->isDirty('state')) {
            // Notify active staff users who are working employees
            $users = User::where('status', true)
                ->whereHas('employees', function ($query) {
                    $query->where('state', EmployeeStateEnum::WORKING);
                })
                ->get();

            Notification::send($users, new OrderStateUpdated($order));
        }
    }
}
