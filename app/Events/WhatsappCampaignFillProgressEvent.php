<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Events;

use App\Models\Comms\WhatsappCampaign;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * How far the merge tag fill has walked, so the campaign page can show progress and keep
 * Send and Schedule disabled until there is nothing left unresolved.
 *
 * Broadcast now rather than queued: this reports on a queued job, and putting it behind
 * the same workers it is reporting on would deliver the progress after the work it
 * describes had already moved on.
 *
 * @param  array{done: int, total: int, state: string, started_at: string|null}  $progress
 */
class WhatsappCampaignFillProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public WhatsappCampaign $campaign, public array $progress)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('grp.'.$this->campaign->group_id.'.whatsapp-campaigns.'.$this->campaign->id),
        ];
    }

    public function broadcastWith(): array
    {
        return $this->progress;
    }

    public function broadcastAs(): string
    {
        return 'whatsapp-campaign.fill-progress';
    }
}
