<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailTrackingEvent;

use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Models\Comms\EmailTrackingEvent;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class PostProcessingEmailTrackingEvent
{
    use AsAction;

    public string $jobQueue = 'analytics';

    public function handle(int $emailTrackingEventId): void
    {

        $emailTrackingEvent = EmailTrackingEvent::find($emailTrackingEventId);
        if (!$emailTrackingEvent) {
            return;
        }

        $data      = $emailTrackingEvent->data;
        $ip        = Arr::pull($data, 'ipAddress');
        $userAgent = Arr::pull($data, 'userAgent');

        $device = null;
        if ($userAgent) {
            $browserData = GetBrowserInfo::run($userAgent);
            $device      = $browserData['device'];
        }

        $emailTrackingEvent->update(
            [
                'ip'     => $ip,
                'device' => $device,
                'data'   => $data,
            ]
        );



    }
}
