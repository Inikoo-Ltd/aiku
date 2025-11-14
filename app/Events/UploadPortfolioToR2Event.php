<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class UploadPortfolioToR2Event implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public string $download_url;
    public string $randomString;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(string $download_url, string $randomString)
    {
        $this->download_url = $download_url;
        $this->randomString = $randomString;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel("upload-portfolio-to-r2.{$this->randomString}");
    }

    public function broadcastWith(): array
    {
        return [
            'download_url' => $this->download_url,
        ];
    }

    public function broadcastAs(): string
    {
        return 'upload-portfolio-to-r2';
    }
}
