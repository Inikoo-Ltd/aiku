<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website\Hydrators;

use App\Models\Web\Website;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class WebsiteHydrateNumberVisitorsLast24Hours implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'analytics';

    public function getJobUniqueId(int $websiteID): string
    {
        return (string) $websiteID;
    }

    public function handle(int $websiteID): ?Website
    {
        $website = Website::find($websiteID);
        if (!$website) {
            return null;
        }

        $website->webStats->update([
            'number_visitors_last_24_hours' => DB::connection('aiku_no_sticky')
                ->table('website_visitors')
                ->where('website_id', $website->id)
                ->where('last_seen_at', '>=', now()->subDay())
                ->count()
        ]);
        return $website;
    }

    public function getCommandSignature(): string
    {
        return 'website:hydrate-visitors-24h {website?}';
    }

    public function asCommand(\Illuminate\Console\Command $command): int
    {
        if ($command->argument('website')) {
            $website = Website::where('slug', $command->argument('website'))->firstOrFail();
            $website = $this->handle($website->id);
            $website->refresh();
            $command->info("Hydrating visitors last 24 hours for website: $website->slug (ID: $website->id) ; Visitors: ".$website->webStats->number_visitors_last_24_hours);

            return 0;
        }

        /** @var Website $website */
        foreach (Website::all() as $website) {
            $website = $this->handle($website->id);
            $website->refresh();
            $command->info("Hydrating visitors last 24 hours for website: $website->slug (ID: $website->id) ; Visitors: ".$website->webStats->number_visitors_last_24_hours);
        }

        return 0;
    }
}
