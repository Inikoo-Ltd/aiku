<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 14:06:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\SysAdmin\Group;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastPalletDeliveryTimeline implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public array $data;

    public Group $group;

    public function __construct(Group $group, PalletDelivery|PalletReturn $parent, string $title, string $text)
    {
        $this->group = $group;
        $this->data = $parent->toArray();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('grp.'.$this->group->id.'.pallet-delivery-timeline'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pallet-delivery-timeline';
    }
}
