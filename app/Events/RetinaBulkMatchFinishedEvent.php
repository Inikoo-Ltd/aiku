<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RetinaBulkMatchFinishedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public int $customerId, public int $customerSalesChannelId, public int $matched, public int $ignored)
    {
    }

    public function broadcastWith(): array
    {
        return [
            'customer_sales_channel_id' => $this->customerSalesChannelId,
            'matched'                   => $this->matched,
            'ignored'                   => $this->ignored,
        ];
    }

    public function broadcastAs(): string
    {
        return 'bulk-match-finished';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('retina.'.$this->customerId.'.customer'),
        ];
    }
}
