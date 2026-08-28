<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailTrackingEvent;

use App\Actions\Comms\DispatchedEmail\Hydrators\DispatchedEmailHydrateClicks;
use App\Actions\Comms\Mailshot\Hydrators\MailshotHydrateDispatchedEmails;
use App\Actions\CRM\TrafficSource\RecordEmailClickTouchpoint;
use App\Actions\CRM\TrafficSource\RecordTrafficSourceClick;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\EmailTrackingEvent\EmailTrackingEventTypeEnum;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Comms\EmailTrackingEvent;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ReclassifyScannerEmailClicks
{
    use AsAction;

    public string $jobQueue = 'ses-analytics';

    /**
     * A mail security scanner clicks every link in a message, so 40 of the first measured day's 46
     * newsletter clicks were Microsoft's scanner and the CTR on the Mailshots and Newsletters
     * screens was fiction. Runs two minutes after the click, once the burst counter written at
     * event time can tell a scanner from a reader; a burst flags the clicks rather than deleting
     * them - the fraud evidence stays - and the stats count only unflagged ones.
     */
    public function handle(int $emailTrackingEventId): void
    {
        $emailTrackingEvent = EmailTrackingEvent::find($emailTrackingEventId);
        if (!$emailTrackingEvent
            || $emailTrackingEvent->type != EmailTrackingEventTypeEnum::CLICKED
            || $emailTrackingEvent->is_scanner
        ) {
            return;
        }

        $ip = $emailTrackingEvent->ip ?? Arr::get($emailTrackingEvent->data, 'ipAddress');

        $dispatchedEmail = $emailTrackingEvent->dispatchedEmail;
        $mailshot        = $dispatchedEmail->sentMailshot;
        $outboxCode      = $dispatchedEmail->outbox?->code;
        $outboxCode      = $outboxCode instanceof \BackedEnum ? $outboxCode->value : $outboxCode;
        $outboxCode      = $mailshot ? null : $outboxCode;

        $campaignRef = match (true) {
            (bool) $mailshot     => (TrafficSourcesTypeEnum::fromMailshotType($mailshot->type?->value ?? $mailshot->type) === TrafficSourcesTypeEnum::NEWSLETTER
                ? RecordEmailClickTouchpoint::CAMPAIGN_REF_PREFIX
                : RecordEmailClickTouchpoint::MARKETING_CAMPAIGN_REF_PREFIX).$mailshot->id,
            $outboxCode !== null => RecordEmailClickTouchpoint::OUTBOX_CAMPAIGN_REF_PREFIX.$outboxCode,
            default              => null,
        };

        if (!$ip || !$campaignRef || !RecordTrafficSourceClick::isScannerBurst($ip, $campaignRef)) {
            return;
        }

        $dispatchedEmail->emailTrackingEvents()
            ->where('type', EmailTrackingEventTypeEnum::CLICKED)
            ->where(function ($query) use ($ip) {
                $query->where('ip', $ip)->orWhere('data->ipAddress', $ip);
            })
            ->update(['is_scanner' => true]);

        DispatchedEmailHydrateClicks::run($dispatchedEmail);
        $dispatchedEmail->refresh();

        /* The CLICKED state was set the moment the webhook arrived; if the scanner turns out to
           own every click, the email falls back to what the reader actually did. UNSUBSCRIBED and
           SPAM outrank clicks and are never touched. */
        if ($dispatchedEmail->number_clicks == 0 && $dispatchedEmail->state == DispatchedEmailStateEnum::CLICKED) {
            $dispatchedEmail->update([
                'state' => $dispatchedEmail->number_reads > 0
                    ? DispatchedEmailStateEnum::OPENED
                    : DispatchedEmailStateEnum::DELIVERED,
            ]);
        }

        if ($mailshot) {
            MailshotHydrateDispatchedEmails::dispatch($mailshot->id);
        }
    }
}
