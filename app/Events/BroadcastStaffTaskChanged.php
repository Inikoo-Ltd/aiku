<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Events;

use App\Models\Tasks\StaffTask;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class BroadcastStaffTaskChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;

    public int $groupId;
    public int $id;
    public string $reference;

    public function __construct(StaffTask $task)
    {
        $this->groupId   = $task->group_id;
        $this->id        = $task->id;
        $this->reference = $task->reference;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('grp.'.$this->groupId.'.general')];
    }

    public function broadcastAs(): string
    {
        return 'staff-task-changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id'        => $this->id,
            'reference' => $this->reference,
        ];
    }
}
