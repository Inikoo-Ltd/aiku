<?php

namespace App\Actions\Web\Website;

use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopHeaderPreview
{
    use AsObject;

    public function handle(Website $website): array
    {
        return [
            'footer' => $website->unpublishedHeaderSnapshot->layout,
        ];
    }
}
