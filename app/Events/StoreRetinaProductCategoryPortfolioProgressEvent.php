<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StoreRetinaProductCategoryPortfolioProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $parentId;
    public int $currentPercentage;
    public int $successCount;
    public int $totalOperations;

    public function __construct(int $parentId, int $currentPercentage, int $successCount, int $totalOperations)
    {
        $this->parentId = $parentId;
        $this->currentPercentage = $currentPercentage;
        $this->successCount = $successCount;
        $this->totalOperations = $totalOperations;
    }

    public function broadcastWith(): array
    {
        return [
            'total' => $this->totalOperations,
            'number_success' => $this->successCount,
            'number_percentage' => $this->currentPercentage,
        ];
    }

    public function broadcastAs(): string
    {
        return 'action-progress';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('retina.pc-clone.' . $this->parentId),
        ];
    }
}
