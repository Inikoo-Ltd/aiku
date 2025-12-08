<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 14:06:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Http\Resources\Helpers\UploadProgressResource;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Upload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UploadRetinaExcelProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public Upload $data;

    public ?WebUser $webUser;

    public function __construct(Upload $upload, ?WebUser $webUser)
    {
        $this->webUser = $webUser;
        $this->data = $upload;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('retina.personal.'.$this->webUser->id),
        ];
    }

    public function broadcastWith(): array
    {
        return UploadProgressResource::make($this->data, $this->webUser)->getArray();
    }

    public function broadcastAs(): string
    {
        return 'action-progress';
    }
}
