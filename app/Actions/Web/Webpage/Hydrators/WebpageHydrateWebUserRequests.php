<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Jun 2025 09:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Hydrators;

use App\Models\Web\Webpage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WebpageHydrateWebUserRequests implements ShouldBeUnique
{
    use AsAction;


    public string $jobQueue = 'analytics';

    public function getJobUniqueId(int $webpageID): string
    {
        return $webpageID;
    }

    public function handle(int $webpageID): void
    {
        $webpage = Webpage::findOrFail($webpageID);
        $stats = [
            'number_web_user_requests' => $webpage->webUserRequests()->count(),
        ];


        $webpage->stats->update($stats);
    }


}
