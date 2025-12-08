<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileDownloadProgress
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $userId;

    public int $progress;

    public ?string $fileName;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($userId, $progress, $fileName = null)
    {
        $this->userId = $userId;
        $this->progress = $progress;
        $this->fileName = $fileName;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn(): Channel|PrivateChannel|array
    {
        return new PrivateChannel('grp.download-progress.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'FileDownloadProgress';
    }
}
