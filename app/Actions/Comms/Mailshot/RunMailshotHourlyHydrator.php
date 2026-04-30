<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 28 Apr 2026 16:48:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\Mailshot\Hydrators\MailshotHydrateDispatchedEmails;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class RunMailshotHourlyHydrator
{
    use AsAction;

    public string $jobQueue = 'ses-analytics';
    public string $commandSignature = 'run-mailshot-hourly-hydrator {organisation?} {--s|shop=}';

    public function handle(?string $organisationSlug = null, ?string $shopSlug = null): void
    {

        $query = Shop::where('state', ShopStateEnum::OPEN->value)
            ->whereIn('type', [ShopTypeEnum::DROPSHIPPING->value, ShopTypeEnum::B2B->value]);

        // Filter by organisation if provided
        if ($organisationSlug) {
            $query->whereHas('organisation', function ($q) use ($organisationSlug) {
                $q->where('slug', $organisationSlug);
            });
        }

        // Filter by shop if provided
        if ($shopSlug) {
            $query->where('slug', $shopSlug);
        }

        $shops = $query->cursor();

        // TODO: need default value or not, if setting not exist
        foreach ($shops as $shop) {
            $marketingHours = $shop->settings['marketing']['hours'] ?? 24; // default 24 hours
            $marketingDays = $shop->settings['marketing']['days'] ?? 30; // default 30 days

            // Calculate date range: now - X days
            $startDate = Carbon::now()->utc()->subDays($marketingDays)->startOfDay();

            // Find mailshots in state 'sending' or 'sent' within the date range
            $mailshots = Mailshot::where('shop_id', $shop->id)
                ->whereIn('state', [MailshotStateEnum::SENDING, MailshotStateEnum::SENT])
                ->where(function ($query) use ($startDate) {
                    $query->where('sent_at', '>=', $startDate)
                        ->orWhere('start_sending_at', '>=', $startDate);
                })
                ->whereNull('deleted_at')
                ->whereNull('source_id') // to avoid resending newsletter that imported from Aurora
                ->whereNull('source_alt_id') // to avoid resending newsletter that imported from Aurora
                ->whereNull('source_alt2_id') // to avoid resending newsletter that imported from Aurora
                ->cursor();

            foreach ($mailshots as $mailshot) {
                $data = $mailshot->data ?? [];
                $lastHydrateAt = $data['last_hourly_hydrate_at'] ?? null;

                // Check if enough hours have passed since last hydration
                if ($lastHydrateAt) {
                    $lastHydrateTime = Carbon::parse($lastHydrateAt)->utc();
                    $hoursSinceLastHydrate = $lastHydrateTime->diffInHours(Carbon::now()->utc());
                    if ($hoursSinceLastHydrate < $marketingHours) {
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

    public function asCommand(Command $command): int
    {
        $organisationSlug = $command->argument('organisation');
        $shopSlug = $command->option('shop');

        $this->handle($organisationSlug, $shopSlug);

        return 0;
    }
}
