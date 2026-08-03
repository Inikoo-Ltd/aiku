<?php

namespace App\Events\Web;

use App\Models\Web\Website;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebsiteVisitorCountUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Website $website,
        public int $loggedInCount,
        public int $loggedOutCount
    ) {
    }

    public function broadcastWith(): array
    {
        return [
            'logged_in_count'  => $this->loggedInCount,
            'logged_out_count' => $this->loggedOutCount,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("website.{$this->website->id}.analytics"),
        ];
    }
}
