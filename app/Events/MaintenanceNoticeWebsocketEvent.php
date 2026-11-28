<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class MaintenanceNoticeWebsocketEvent implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array{starts_at: string, minutes: int}|null  $notice
     */
    public function __construct(public ?array $notice)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('app.general');
    }

    public function broadcastAs(): string
    {
        return 'maintenance-notice';
    }

    /**
     * @return array{notice: array{starts_at: string, minutes: int}|null}
     */
    public function broadcastWith(): array
    {
        return ['notice' => $this->notice];
    }
}
