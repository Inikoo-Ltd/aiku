<?php

namespace App\Events\Web;

use App\Models\Web\Website;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebsiteVisitorHit implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Website $website,
        public array $visitorData
    ) {
    }

    public function broadcastWith(): array
    {
        return $this->visitorData;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("website.{$this->website->id}.analytics"),
        ];
    }
}
