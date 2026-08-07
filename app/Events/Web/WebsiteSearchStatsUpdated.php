<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events\Web;

use App\Actions\Search\GetWebsiteSearchAnalytics;
use App\Models\Web\Website;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebsiteSearchStatsUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Website $website)
    {
    }

    /**
     * Only the headline numbers travel. Rebuilding the lists and the trend on every search
     * would be far heavier, and they are not what someone watching the page sees move.
     */
    public function broadcastWith(): array
    {
        return GetWebsiteSearchAnalytics::headline($this->website);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("website.{$this->website->id}.analytics"),
        ];
    }
}
