<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\SysAdmin\Group;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells open AI dashboards that the AI time series moved, so they reload their figures.
 */
class BroadcastAiUsageChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function broadcastOn(): array
    {
        return Group::pluck('id')->map(fn (int $groupId) => new PrivateChannel('grp.'.$groupId.'.general'))->all();
    }

    public function broadcastAs(): string
    {
        return 'ai-usage-changed';
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
