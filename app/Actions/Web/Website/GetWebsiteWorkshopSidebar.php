<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 12 Sep 2025 15:38:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopSidebar
{
    use AsObject;

    public function handle(Website $website): array
    {
        if (!Arr::get($website->unpublishedSidebarSnapshot, 'layout.sidebar')) {

            return [
                'sidebar'    => Arr::get($website->published_layout, 'sidebar', [])
            ];
        }

        return [
            'sidebar'    => Arr::get($website->unpublishedSidebarSnapshot, 'layout.sidebar', [])
        ];
    }
}
