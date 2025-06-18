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

class ExternalLinkHydrateWebpages implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(ExternalLink $externalLink): string
    {
        return $externalLink->id;
    }

    public function handle(ExternalLink $externalLink): void
    {
        $data = [
            'number_webpages_shown'    => $externalLink->webpages()->wherePivot('show', true)->count(),
            'number_webpages_hidden'   => $externalLink->webpages()->wherePivot('show', false)->count(),
        ];

        $externalLink->update($data);
    }
}
