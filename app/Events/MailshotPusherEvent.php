<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 Nov 2023 14:43:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Http\Resources\Mail\MailshotResource;
use App\Models\Comms\Mailshot;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MailshotPusherEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    private Mailshot $mailshot;

    public function __construct(Mailshot $mailshot)
    {
        $this->mailshot = $mailshot;

    }

    public function broadcastWith()
    {
        return MailshotResource::make($this->mailshot)->getArray();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('grp.general'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'mailshot.'.$this->mailshot->id;
    }
}
