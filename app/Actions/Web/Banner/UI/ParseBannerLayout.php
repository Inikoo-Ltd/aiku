<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner\UI;

use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ParseBannerLayout
{
    use AsAction;

    public function handle(array $layout): array
    {
        $slides = [];

        foreach (Arr::get($layout, 'components', []) as $key => $slideData) {
            $slides[Arr::get($slideData, 'ulid', $key)] = [
                'layout' => Arr::get($slideData, 'layout'),
                'visibility' => Arr::get($slideData, 'visibility'),
                'image_id' => Arr::get($slideData, 'image.desktop.id'),
                'mobile_image_id' => Arr::get($slideData, 'image.mobile.id'),
                'tablet_image_id' => Arr::get($slideData, 'image.tablet.id'),
            ];
        }

        return [
            Arr::except($layout, ['components', 'images']),
            $slides,
        ];
    }
}
