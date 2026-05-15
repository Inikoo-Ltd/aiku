<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website\Hydrators;

use App\Models\Web\Website;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WebsiteHydrateLastVisitedAt implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'analytics';

    public function getJobUniqueId(int $websiteID): string
    {
        return (string) $websiteID;
    }

    public function handle(int $websiteID): void
    {
        $website = Website::findOrFail($websiteID);

        $website->update([
            'last_visited_at' => $website->visitors()->max('last_seen_at'),
        ]);
    }
}
