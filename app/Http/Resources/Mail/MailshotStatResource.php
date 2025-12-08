<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 Nov 2023 15:13:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Mail;

use App\Http\Resources\HasSelfCall;
use App\Models\Comms\MailshotStats;
use Illuminate\Http\Resources\Json\JsonResource;

class MailshotStatResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var MailshotStats $mailshotStatsStats */
        $mailshotStatsStats = $this;

        return [
            'number_dispatched_emails' => $mailshotStatsStats->number_dispatched_emails,
            'number_error_emails' => $mailshotStatsStats->number_dispatched_emails_state_error,
            'number_rejected_emails' => $mailshotStatsStats->number_dispatched_emails_state_rejected,
            'number_sent_emails' => $mailshotStatsStats->number_dispatched_emails_state_sent,
            'number_delivered_emails' => $mailshotStatsStats->number_dispatched_emails_state_delivered,
            'number_hard_bounced_emails' => $mailshotStatsStats->number_dispatched_emails_state_hard_bounce,
            'number_soft_bounced_emails' => $mailshotStatsStats->number_dispatched_emails_state_soft_bounce,
            'number_opened_emails' => $mailshotStatsStats->number_dispatched_emails_state_opened,
            'number_clicked_emails' => $mailshotStatsStats->number_dispatched_emails_state_clicked,
            'number_spam_emails' => $mailshotStatsStats->number_dispatched_emails_state_spam,
            'number_unsubscribed_emails' => $mailshotStatsStats->number_dispatched_emails_state_unsubscribed,
        ];
    }
}
