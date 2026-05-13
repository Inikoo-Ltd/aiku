<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 14:06:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FetchProductFromPlatformProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;


    public ShopifyUser|TiktokUser $platformUser;
    public array $modelData;

    public function __construct(ShopifyUser|TiktokUser $platformUser, array $modelData)
    {
        $this->platformUser     = $platformUser;
        $this->modelData     = $modelData;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("platform.{$this->platformUser->id}.fetch-product")
        ];
    }

    public function broadcastWith(): array
    {
        return $this->modelData;
    }

    public function broadcastAs(): string
    {
        return 'platform-fetch-progress';
    }
}
