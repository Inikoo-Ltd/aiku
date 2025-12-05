<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailBulkRun\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\EmailBulkRun;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class EmailBulkRunHydrateDispatchedEmails implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;


    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(EmailBulkRun $emailBulkRun): string
    {
        return $emailBulkRun->id;
    }


    public function handle(EmailBulkRun $emailBulkRun): void
    {
        $stats = [
            'number_dispatched_emails' => $emailBulkRun->dispatchedEmails()->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'dispatched_emails',
                field: 'state',
                enum: DispatchedEmailStateEnum::class,
                models: DispatchedEmail::class,
                where: function ($q) use ($emailBulkRun) {
                    $q->where('parent_type', get_class($emailBulkRun));
                    $q->where('parent_id', $emailBulkRun->id);
                }
            )
        );

        $emailBulkRun->stats()->update($stats);
    }
}
