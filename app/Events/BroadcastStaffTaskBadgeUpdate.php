<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class BroadcastStaffTaskBadgeUpdate implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param array<string, mixed> $taskBadges
     */
    public function __construct(public int $userId, public array $taskBadges)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('grp.personal.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'task-badges-update';
    }

    public function broadcastWith(): array
    {
        return ['task_badges' => $this->taskBadges];
    }
}
