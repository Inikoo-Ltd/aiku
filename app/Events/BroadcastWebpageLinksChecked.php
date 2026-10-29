<?php

/*
 * Created: Tue, 27 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class BroadcastWebpageLinksChecked implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public int $userId,
        public int $webpageId,
        public int $brokenLinks
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('grp.personal.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'webpage-links-checked';
    }

    public function broadcastWith(): array
    {
        return [
            'webpage_id'   => $this->webpageId,
            'broken_links' => $this->brokenLinks,
        ];
    }
}
