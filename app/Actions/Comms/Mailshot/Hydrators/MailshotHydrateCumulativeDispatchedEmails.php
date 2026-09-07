<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\Hydrators;

use App\Actions\Traits\WithArchivedDispatchedEmails;
use App\Models\Comms\Mailshot;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MailshotHydrateCumulativeDispatchedEmails implements ShouldBeUnique
{
    use AsAction;
    use WithArchivedDispatchedEmails;


    public string $jobQueue = 'hydrators-slave';

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

        $stats['number_try_send_total']     = $stats['number_try_send_failure'] + $stats['number_try_send_success'];
        $stats['number_deliveries_failure'] = $mailshotStats->number_dispatched_emails_state_hard_bounce +
            $mailshotStats->number_dispatched_emails_state_soft_bounce;
        $stats['number_deliveries_success'] = $stats['number_try_send_success'] - $stats['number_deliveries_failure'];

        $engagement = DB::table('dispatched_emails')
            ->join('mailshot_has_dispatched_emails', 'mailshot_has_dispatched_emails.dispatched_email_id', '=', 'dispatched_emails.id')
            ->where('mailshot_id', $mailshot->id)
            ->selectRaw('count(*) filter (where number_reads > 0) as opened, count(*) filter (where number_clicks > 0) as clicked')
            ->first();

        $engagementStats = $this->addArchivedDispatchedEmails($mailshotStats, [
            'number_delivered_open_success'  => $engagement->opened,
            'number_opened_interact_success' => $engagement->clicked,
        ]);

        $stats['number_delivered_open_success'] = $engagementStats['number_delivered_open_success'];
        $stats['number_delivered_open_failure'] = $stats['number_deliveries_success'] - $stats['number_delivered_open_success'];

        $stats['number_opened_interact_success'] = $engagementStats['number_opened_interact_success'];
        $stats['number_opened_interact_failure'] = $stats['number_delivered_open_success'] - $stats['number_opened_interact_success'];

        $mailshot->stats->update($stats);
    }
}
