<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026 18:17:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Actions\DevOps\UI\ShowDevopsDashboard;
use App\Models\DevOps\CiRun;
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

    public function __construct(public CiRun $ciRun)
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
        if ($this->ciRun->workflow !== ShowDevopsDashboard::DEPLOY_WORKFLOW) {
            return ['github_run_id' => $this->ciRun->github_run_id];
        }

        $deploy = ShowDevopsDashboard::make()->ciRunDetail($this->ciRun);

        return [
            'github_run_id' => $this->ciRun->github_run_id,
            'deploy'        => [
                'status'       => $deploy['status'],
                'conclusion'   => $deploy['conclusion'],
                'head_message' => $deploy['head_message'],
                'deploy_done'  => $deploy['deploy_done'],
                'deploy_total' => $deploy['deploy_total'],
            ],
        ];
    }
}
