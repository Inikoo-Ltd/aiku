<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\Hydrators;

use App\Models\Comms\Mailshot;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MailshotHydrateCumulativeDispatchedEmails implements ShouldBeUnique
{
    use AsAction;


    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Mailshot $mailshot): string
    {
        return $mailshot->id;
    }


    public function handle(Mailshot $mailshot): void
    {
        $mailshotStats = $mailshot->stats;

        $stats['number_try_send_failure'] = $mailshotStats->number_dispatched_emails_state_error +
            $mailshotStats->number_dispatched_emails_state_rejected_by_provider +
            $mailshotStats->number_dispatched_emails_state_rejected_by_provider;
        $stats['number_try_send_success'] = $mailshotStats->number_dispatched_emails_state_delivered +
            $mailshotStats->number_dispatched_emails_state_hard_bounce +
            $mailshotStats->number_dispatched_emails_state_soft_bounce +
            $mailshotStats->number_dispatched_emails_state_opened +
            $mailshotStats->number_dispatched_emails_state_clicked +
            $mailshotStats->number_dispatched_emails_state_spam +
            $mailshotStats->number_dispatched_emails_state_unsubscribed;

        $stats['number_try_send_total'] = $stats['number_try_send_failure'] + $stats['number_try_send_success'];
        $stats['number_deliveries_failure'] = $mailshotStats->number_dispatched_emails_state_hard_bounce +
            $mailshotStats->number_dispatched_emails_state_soft_bounce ;
        $stats['number_deliveries_success'] = $stats['number_try_send_success'] - $stats['number_deliveries_failure'];

        $baseQuery = DB::table('dispatched_emails')
            ->where('parent_type', 'Mailshot')
            ->where('parent_id', $mailshot->id);

        $openedDispatchedEmails = $baseQuery
            ->where('number_reads', '>', 0)->count();

        $stats['number_delivered_open_success'] = $openedDispatchedEmails;
        $stats['number_delivered_open_failure'] = $stats['number_deliveries_success'] - $openedDispatchedEmails;

        $interactDispatchedEmails = $baseQuery
            ->where('number_clicks', '>', 0)->count();

        $stats['number_opened_interact_success'] = $interactDispatchedEmails;
        $stats['number_opened_interact_failure'] = $stats['number_delivered_open_success'] - $interactDispatchedEmails;

        $mailshot->stats->update($stats);
    }
}
