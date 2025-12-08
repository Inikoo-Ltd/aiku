<?php

namespace App\Actions\Web\Website;

use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopHeader
{
    use AsObject;

    public function handle(Website $website): array
    {
        return [
            'data' => Arr::get($website->unpublishedHeaderSnapshot->layout, 'header'),
        ];
    }
}
