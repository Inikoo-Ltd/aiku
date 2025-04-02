<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Mar 2025 08:33:15 Malaysia Time, Changu, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\DispatchedEmail\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Comms\EmailTrackingEvent\EmailTrackingEventTypeEnum;
use App\Models\Comms\DispatchedEmail;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class DispatchedEmailHydrateReads implements ShouldBeUnique
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
            'number_reads' => $dispatchedEmail
                ->emailTrackingEvents()
                ->where('type', EmailTrackingEventTypeEnum::OPENED)
                ->count(),
        ];

        $dispatchedEmail->update($stats);
    }
}
