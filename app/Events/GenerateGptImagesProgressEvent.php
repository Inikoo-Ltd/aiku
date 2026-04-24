<?php

namespace App\Events;

use App\Http\Resources\Helpers\ImageResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class GenerateGptImagesProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public array $images;
    private int $userId;

    public function __construct(array $images, int $userId)
    {
        $this->userId = $userId;
        $this->images = $images;
    }

    public function broadcastWith(): array
    {
        if(blank($this->images)) {
            return [
                'images' => [],
                'status' => 'failed'
            ];
        }

        return ImageResource::make(Arr::first($this->images))->getArray();
    }

    public function broadcastAs(): string
    {
        return 'action-progress';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('retina.image.generation.' . $this->userId)
        ];
    }
}
