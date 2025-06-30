<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Jun 2025 09:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Hydrators;

use App\Models\Web\Website;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WebsiteHydrateWebUserRequests implements ShouldBeUnique
{
    use AsAction;


    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(int $websiteID): string
    {
        return $websiteID;
    }

    public function handle(int $websiteID): void
    {
        $website = Website::findOrFail($websiteID);
        $stats   = [
            'number_web_user_requests' => $website->webUserRequests()->count(),
        ];


        $website->webStats->update($stats);
    }


}
