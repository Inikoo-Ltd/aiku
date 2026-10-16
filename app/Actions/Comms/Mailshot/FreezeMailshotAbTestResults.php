<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 15 Oct 2026 10:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\Mailshot\Hydrators\MailshotHydrateDispatchedEmails;
use App\Actions\Comms\Mailshot\Hydrators\MailshotHydratePurchases;
use App\Actions\Comms\Mailshot\UI\GetMailshotAbTest;
use App\Enums\Comms\EmailTrackingEvent\EmailTrackingEventTypeEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FreezeMailshotAbTestResults
{
    use AsAction;

    /**
     * Once tracking is over, each version's comparison is stored in its data.AB_testing_data, so opens, clicks and orders
     * after the test, and the winner's sends to the rest of the list, only show in the mailshot's own stats.
     */
    public function handle(Mailshot $mailshot): void
    {
        $trackingEndsAt = $mailshot->abTestTrackingEndsAt();
        if (!$trackingEndsAt || $trackingEndsAt->isFuture() || Arr::has($mailshot->data, 'AB_testing_data')) {
            return;
        }

        $results = collect([$mailshot, $mailshot->abTestVarFreezeMailshotAbTestResultsiant])->mapWithKeys(function (Mailshot $version) use ($trackingEndsAt) {
            MailshotHydrateDispatchedEmails::run($version->id);

            return [$version->id => $this->results($version->refresh(), $trackingEndsAt)];
        });

        DB::transaction(function () use ($results) {
            foreach ($results as $mailshotId => $testingData) {
                Mailshot::whereKey($mailshotId)->whereNull('data->AB_testing_data')->update(['data->AB_testing_data' => $testingData]);
            }
        });

        $mailshot->refresh();
    }

    /**
     * @return array{tracked_until: string, stats: array<string, int>}
     */
    private function results(Mailshot $mailshot, Carbon $trackingEndsAt): array
    {
        $engagement = DB::table('email_tracking_events')
            ->join('mailshot_has_dispatched_emails', 'mailshot_has_dispatched_emails.dispatched_email_id', '=', 'email_tracking_events.dispatched_email_id')
            ->where('mailshot_has_dispatched_emails.mailshot_id', $mailshot->id)
            ->where('email_tracking_events.created_at', '<=', $trackingEndsAt)
            ->selectRaw('count(distinct email_tracking_events.dispatched_email_id) filter (where email_tracking_events.type = ?) as opened', [EmailTrackingEventTypeEnum::OPENED->value])
            ->selectRaw('count(distinct email_tracking_events.dispatched_email_id) filter (where email_tracking_events.type = ? and not email_tracking_events.is_scanner) as clicked', [EmailTrackingEventTypeEnum::CLICKED->value])
            ->first();

        return [
            'tracked_until' => $trackingEndsAt->toIso8601String(),
            'stats'         => array_merge(GetMailshotAbTest::make()->currentCounts($mailshot), [
                'opened'        => (int)$engagement->opened,
                'clicked'       => (int)$engagement->clicked,
                'placed_orders' => MailshotHydratePurchases::make()->purchasedRecipients($mailshot, $trackingEndsAt)->count(),
            ]),
        ];
    }
}
