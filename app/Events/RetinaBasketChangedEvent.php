<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A basket's total changed, by the customer in another tab, the stock sync, offers or staff.
 * A checkout page open on that basket reloads, so its card payment session is created again
 * for the new amount instead of charging the one priced when the page opened (PRE-64, PRE-126).
 * Sent from CalculateOrderTotalAmounts once the edit commits, so the reloaded page reads the new lines;
 * a failed broadcast is swallowed so it never stops the recalculation.
 */
class RetinaBasketChangedEvent implements ShouldBroadcastNow, ShouldRescue, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public int $customerId, public int $orderId)
    {
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
        ];
    }

    public function broadcastAs(): string
    {
        return 'basket-changed';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('retina.'.$this->customerId.'.customer'),
        ];
    }
}
