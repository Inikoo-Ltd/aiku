<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox\DueToReorder;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\Outbox;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RunDueToReorderEmailBulkRuns
{
    use AsAction;

    public string $commandSignature = 'run:due-to-reorder-email-bulk-runs';
    public string $jobQueue = 'ses';

    public function handle(): void
    {
        $outboxes = Outbox::where('code', OutboxCodeEnum::DUE_TO_REORDER)
            ->where('state', OutboxStateEnum::ACTIVE)
            ->whereNotNull('shop_id')
            ->get();

        foreach ($outboxes as $outbox) {
            ProcessDueToReorderPerOutbox::dispatch($outbox);
        }
    }

    public function asCommand(): void
    {
        Nightwatch::dontSample();
        $this->handle();
    }
}
