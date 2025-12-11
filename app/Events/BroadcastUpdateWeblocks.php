<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 14:06:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Web\Website;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastUpdateWeblocks implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
    public int|float $percent;
    public Website $website;

    public function __construct(int|float $percent, Website $website)
    {
        $this->percent = $percent;
        $this->website = $website;
    }

    public function broadcastWith(): array
    {
        return [
            'percent' => $this->percent
        ];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("updateWebblocks.{$this->website->slug}");
    }

    public function broadcastAs(): string
    {
        return 'progress';
    }
}
