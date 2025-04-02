<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 20 Feb 2025 13:37:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\DispatchedEmail\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Comms\DispatchedEmail;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class DispatchedEmailHydrateEmailTracking implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(DispatchedEmail $dispatchedEmail): string
    {
        return $dispatchedEmail->id;
    }

    public function handle(DispatchedEmail $dispatchedEmail): void
    {
        $stats = [
            'number_email_tracking_events' => $dispatchedEmail
                ->emailTrackingEvents()
                ->count()
        ];

        $dispatchedEmail->update($stats);
    }
}
