<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner\UI;

use App\Models\Web\Banner;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsController;

class DeliverBanner
{
    use AsController;

    public function handle(string $slug): array
    {
        $seconds = 86400;
        return Cache::remember('banner_compiled_layout_'.$slug, $seconds, function () use ($slug) {
            $banner = Banner::where('slug', $slug)->firstOrFail();
            return $banner->compiled_layout;
        });

    }




    public function htmlResponse(array $compiledLayout): Response
    {
        return Inertia::render(
            'Banner',
            [
                'data' => $compiledLayout
            ]
        );
    }

}
