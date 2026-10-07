<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026 18:17:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\SysAdmin\Group;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Cache;

class BroadcastCiRunUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public int $githubRunId)
    {
    }

    public function broadcastOn(): array
    {
        $groupIds = Cache::remember('devops-server-live-group-ids', 3600, fn () => Group::pluck('id')->all());

        return array_map(fn (int $groupId) => new PrivateChannel('grp.'.$groupId.'.devops.ci'), $groupIds);
    }

    public function broadcastAs(): string
    {
        return 'ci-run-updated';
    }

    public function broadcastWith(): array
    {
        return ['github_run_id' => $this->githubRunId];
    }
}
