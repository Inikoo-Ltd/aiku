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


    public string $jobQueue = 'analytics';

    public function getJobUniqueId(?int $emailBulkRunId): string
    {
        return $emailBulkRunId ?? 'empty';
    }


    public function handle(?int $emailBulkRunId): void
    {
        if (!$emailBulkRunId) {
            return;
        }
        $emailBulkRun = EmailBulkRun::find($emailBulkRunId);
        if (!$emailBulkRun) {
            return;
        }

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
                    $q->leftJoin('email_bulk_run_has_dispatched_emails', 'email_bulk_run_has_dispatched_emails.dispatched_email_id', '=', 'dispatched_emails.id');
                    $q->where('email_bulk_run_id', $emailBulkRun->id);
                }
            )
        );

        $emailBulkRun->stats()->update($stats);
    }
}
