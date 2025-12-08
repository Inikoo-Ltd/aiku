<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 14:06:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Http\Resources\Helpers\UploadProgressResource;
use App\Models\Helpers\Upload;
use App\Models\SysAdmin\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UploadExcelProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public Upload $data;

    public ?User $user;

    public function __construct(Upload $upload, ?User $user)
    {
        $this->user = $user;
        $this->data = $upload;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('grp.personal.'.$this->user->id),
        ];
    }

    public function broadcastWith(): array
    {
        return UploadProgressResource::make($this->data, $this->user)->getArray();
    }

    public function broadcastAs(): string
    {
        return 'action-progress';
    }
}
