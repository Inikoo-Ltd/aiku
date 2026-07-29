<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Jul 2026 16:48:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\DevOps\AppDeployment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppVersionWebsocketEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;


    public function __construct(public ?AppDeployment $appDeployment = null)
    {
    }


    public function broadcastOn(): \Illuminate\Broadcasting\Channel|PrivateChannel|array
    {
        return new PrivateChannel('app.general');
    }

    public function broadcastAs(): string
    {
        return 'post-deployed';
    }

    /**
     * @return array{deployment: array{semantic_version: string|null, change_log: string|null, committers: array<int, array{name: string, email: string, github_username: string|null, avatar: string|null}>|null, deployed_at: string|null}|null}
     */
    public function broadcastWith(): array
    {
        return [
            'deployment' => $this->appDeployment ? [
                'semantic_version' => $this->appDeployment->semantic_version,
                'change_log'       => $this->appDeployment->change_log,
                'committers'       => $this->appDeployment->committers,
                'deployed_at'      => $this->appDeployment->created_at?->toIso8601String(),
            ] : null,
        ];
    }
}
