<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 14:06:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Http\Resources\Web\WebBlockTypesResource;
use App\Http\Resources\Web\WebpageResource;
use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastPreviewWebpage implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public array $data;

    public Webpage $webpage;

    public function __construct(Webpage $webpage)
    {
        $this->webpage = $webpage;
    }

    public function broadcastWith(): array
    {
        return [
            'webpage' => WebpageResource::make($this->webpage)->getArray(),
            'webBlockTypes' => WebBlockTypesResource::collection(WebBlockType::all()),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('webpage.'.$this->webpage->slug.'.preview'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'WebpagePreview';
    }
}
