<?php

namespace App\Actions\Web\Website;

use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopFooterPreview
{
    use AsObject;

    public function handle(Website $website): array
    {
        return [
            'footer' => $website->unpublishedFooterSnapshot->layout,
        ];
    }
}
