<?php

/*
 * Author Louis Perez
 * Created on 13-07-2026-08h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Events;

use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastTransactionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public array $data;
    private Order $order;

    public function __construct(Transaction $transaction, Order $order)
    {
        $this->order = $order;

        $this->data     = [
            'title'             => "Order has been modified",
            'body'              => "One or more product has been modified, data will be updated accordingly",
        ];
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("grp.{$this->order->slug}.transaction_update");
    }

    public function broadcastAs(): string
    {
        return 'transaction_update';
    }
}
