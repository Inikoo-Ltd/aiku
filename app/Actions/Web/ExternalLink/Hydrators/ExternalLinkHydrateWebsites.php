<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Web\ExternalLink\Hydrators;

use App\Models\Web\ExternalLink;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ExternalLinkHydrateWebsites implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(ExternalLink $externalLink): string
    {
        return $externalLink->id;
    }

    public function handle(ExternalLink $externalLink): void
    {
        $data = [
            'number_websites_shown'    => $externalLink->websites()->wherePivot('show', true)->count(),
            'number_websites_hidden'   => $externalLink->websites()->wherePivot('show', false)->count(),
        ];

        $externalLink->update($data);
    }
}
