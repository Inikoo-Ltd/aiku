<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailBulkRun\Hydrators;

use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Models\Comms\EmailBulkRun;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class EmailBulkRunHydrateCumulativeDispatchedEmails implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(EmailBulkRun $emailBulkRun): string
    {
        return $emailBulkRun->id;
    }

    public function handle(EmailBulkRun $emailBulkRun, DispatchedEmailStateEnum $state): void
    {
        if ($state == DispatchedEmailStateEnum::READY) {
            EmailBulkRunHydrateDispatchedEmails::run($emailBulkRun);

            return;
        }

        /** @noinspection PhpUncoveredEnumCasesInspection */
        $count = DB::table('dispatched_emails')
            ->where('emailBulkRun_id', $emailBulkRun->id)->where('is_test', false)
            ->where(
                match ($state) {
                    DispatchedEmailStateEnum::ERROR => 'is_error',
                    DispatchedEmailStateEnum::REJECTED_BY_PROVIDER => 'is_rejected',
                    DispatchedEmailStateEnum::SENT => 'is_sent',
                    DispatchedEmailStateEnum::DELIVERED => 'is_delivered',
                    DispatchedEmailStateEnum::HARD_BOUNCE => 'is_hard_bounced',
                    DispatchedEmailStateEnum::SOFT_BOUNCE => 'is_soft_bounced',
                    DispatchedEmailStateEnum::OPENED => 'is_opened',
                    DispatchedEmailStateEnum::CLICKED => 'is_clicked',
                    DispatchedEmailStateEnum::SPAM => 'is_spam',
                    DispatchedEmailStateEnum::UNSUBSCRIBED => 'is_unsubscribed',
                },
                true
            )->count();


        /** @noinspection PhpUncoveredEnumCasesInspection */
        $emailBulkRun->stats()->update(
            [
                match ($state) {
                    DispatchedEmailStateEnum::ERROR => 'number_error_emails',
                    DispatchedEmailStateEnum::REJECTED_BY_PROVIDER => 'number_rejected_emails',
                    DispatchedEmailStateEnum::SENT => 'number_sent_emails',
                    DispatchedEmailStateEnum::DELIVERED => 'number_delivered_emails',
                    DispatchedEmailStateEnum::HARD_BOUNCE => 'number_hard_bounced_emails',
                    DispatchedEmailStateEnum::SOFT_BOUNCE => 'number_soft_bounced_emails',
                    DispatchedEmailStateEnum::OPENED => 'number_opened_emails',
                    DispatchedEmailStateEnum::CLICKED => 'number_clicked_emails',
                    DispatchedEmailStateEnum::SPAM => 'number_spam_emails',
                    DispatchedEmailStateEnum::UNSUBSCRIBED => 'number_unsubscribed_emails',
                }

                => $count
            ]
        );
    }
}
