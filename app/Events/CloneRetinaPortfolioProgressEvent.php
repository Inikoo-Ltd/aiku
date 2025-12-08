<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CloneRetinaPortfolioProgressEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $userId;
    public int $actionId;
    public string $actionType;
    public int $total;
    public int $done;
    public int $numberSuccess;
    public int $numberFails;

    public function __construct(int $userId, int $actionId, string $actionType, int $total, int $done, int $numberSuccess, int $numberFails)
    {
        $this->userId = $userId;
        $this->actionId = $actionId;
        $this->actionType = $actionType;
        $this->total = $total;
        $this->done = $done;
        $this->numberSuccess = $numberSuccess;
        $this->numberFails = $numberFails;
    }

    public function broadcastWith(): array
    {
        return [
            'action_id' => $this->actionId,
            'action_type' => $this->actionType,
            'total' => $this->total,
            'done' => $this->done,
            'data' => [
                'number_success' => $this->numberSuccess,
                'number_fails' => $this->numberFails,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'action-progress';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('grp.personal.' . $this->userId),
        ];
    }
}
