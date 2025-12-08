<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 14:06:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\SysAdmin\Group;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastUserNotification implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public array $data;

    public Group $group;

    public function __construct(Group $group, PalletDelivery|PalletReturn|Pallet $parent, string $title, string $text)
    {
        $this->group = $group;
        $this->data = [
            'title' => $title,
            'body' => $text,
            'type' => class_basename($parent),
            'id' => $parent->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('grp.'.$this->group->id.'.general'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification';
    }
}
