<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Mailshot;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MailshotHydrateDispatchedEmails implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'analytics';

    public function getJobUniqueId(?int $mailshotId): string
    {
        return $mailshotId ?? 'empty';
    }

    public function handle(?int $mailshotId): void
    {
        if (!$mailshotId) {
            return;
        }
        $mailshot = Mailshot::find($mailshotId);
        if (!$mailshot) {
            return;
        }
        $stats = [
            'number_dispatched_emails' => $mailshot->dispatchedEmails()->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'dispatched_emails',
                field: 'state',
                enum: DispatchedEmailStateEnum::class,
                models: DispatchedEmail::class,
                where: function ($q) use ($mailshot) {
                    $q->leftJoin('mailshot_has_dispatched_emails', 'mailshot_has_dispatched_emails.dispatched_email_id', '=', 'dispatched_emails.id');
                    $q->where('mailshot_id', $mailshot->id);
                }
            )
        );

        $mailshot->stats()->update($stats);
        MailshotHydrateCumulativeDispatchedEmails::run($mailshot);
    }
}
