<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Mar 2026 00:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Comms\Mailshot;
use App\Models\SysAdmin\Group;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastMailshotStats implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public array $data;
    public Group $group;

    public function __construct(Group $group, Mailshot $mailshot, array $stats)
    {
        $this->group = $group;
        $this->data  = [
            'mailshot_id' => $mailshot->id,
            'stats'       => $stats,
            'state'       => $mailshot->state,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('grp.'.$this->group->id.'.mailshots.'.$this->data['mailshot_id']),
        ];
    }

    public function broadcastAs(): string
    {
        return 'mailshot.stats.updated';
    }
}
