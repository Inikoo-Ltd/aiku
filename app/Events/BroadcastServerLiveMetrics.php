<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026 04:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\SysAdmin\Group;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Cache;

class BroadcastServerLiveMetrics implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public string $slug, public array $reading)
    {
    }

    public function broadcastOn(): array
    {
        $groupIds = Cache::remember('devops-server-live-group-ids', 3600, fn () => Group::pluck('id')->all());

        return array_map(fn (int $groupId) => new PrivateChannel('grp.'.$groupId.'.devops.servers'), $groupIds);
    }

    public function broadcastAs(): string
    {
        return 'server-live-metrics';
    }

    public function broadcastWith(): array
    {
        return ['slug' => $this->slug, ...$this->reading];
    }
}
