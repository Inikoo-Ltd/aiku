<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 14:06:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Actions\Web\Website\GetWebsiteWorkshopFooter;
use App\Actions\Web\Website\GetWebsiteWorkshopHeader;
use App\Actions\Web\Website\GetWebsiteWorkshopMenuPreview;
use App\Models\Web\Website;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastPreviewHeaderFooter implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public array $data;

    public Website $website;

    public function __construct(Website $website)
    {
        $this->website = $website;
    }

    public function broadcastWith(): array
    {
        return [
            'header' => GetWebsiteWorkshopHeader::run($this->website),
            'footer' => GetWebsiteWorkshopFooter::run($this->website),
            'navigation' => GetWebsiteWorkshopMenuPreview::run($this->website),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('header-footer.'.$this->website->slug.'.preview'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'WebpagePreview';
    }
}
