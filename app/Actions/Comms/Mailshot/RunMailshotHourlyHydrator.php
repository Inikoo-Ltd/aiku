<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 28 Apr 2026 16:48:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\Mailshot\Hydrators\MailshotHydrateDispatchedEmails;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class RunMailshotHourlyHydrator
{
    use AsAction;

    public string $jobQueue = 'analytics';
    public string $commandSignature = 'run-mailshot-hourly-hydrator';

    public function handle(): void
    {
        $currentHour = Carbon::now()->utc()->hour;

        // Get all shops with marketing.hours configured
        $shops = Shop::whereNotNull('settings->marketing->hours')->cursor();

        foreach ($shops as $shop) {
            $marketingHours = $shop->settings['marketing']['hours'] ?? null;
            $marketingDays = $shop->settings['marketing']['days'] ?? null;

            if (!$marketingHours || !$marketingDays) {
                continue;
            }

            // Check if current hour matches the schedule (e.g., 4:00, 8:00, 12:00 for hours=4)
            if ($currentHour % $marketingHours !== 0) {
                continue;
            }

            // Calculate date range: now - X days
            $startDate = Carbon::now()->utc()->subDays($marketingDays)->startOfDay();

            // Find mailshots in state 'sending' or 'sent' within the date range
            $mailshots = Mailshot::where('shop_id', $shop->id)
                ->whereIn('state', [MailshotStateEnum::SENDING, MailshotStateEnum::SENT])
                ->where('sent_at', '>=', $startDate)
                ->whereNull('deleted_at')
                ->cursor();

            foreach ($mailshots as $mailshot) {
                $data = $mailshot->data ?? [];
                $lastHydrateAt = $data['last_hourly_hydrate_at'] ?? null;

                // Check if already hydrated this hour
                if ($lastHydrateAt) {
                    $lastHydrateTime = Carbon::parse($lastHydrateAt)->utc();
                    if ($lastHydrateTime->hour === $currentHour && $lastHydrateTime->isSameDay(Carbon::now()->utc())) {
                        continue;
                    }
                }

                // Dispatch hydrator job
                MailshotHydrateDispatchedEmails::dispatch($mailshot->id);

                // Update last hydration timestamp
                $mailshot->update([
                    'data' => array_merge($data, [
                        'last_hourly_hydrate_at' => Carbon::now()->utc()->toIso8601String()
                    ])
                ]);
            }
        }
    }
}
