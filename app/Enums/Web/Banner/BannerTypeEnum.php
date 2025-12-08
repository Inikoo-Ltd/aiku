<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Oct 2023 15:08:27 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Web\Banner;

use App\Enums\EnumHelperTrait;
use App\Models\Web\Website;

enum BannerTypeEnum: string
{
    use EnumHelperTrait;

    case LANDSCAPE = 'landscape';
    case SQUARE = 'square';

    public static function labels(): array
    {
        return [
            'landscape' => __('landscape'),
            'square' => __('square'),
            // 'portrait'        => __('portrait'),

        ];
    }

    public static function count(Website $parent): array
    {
        $stats = $parent->webStats;

        return [
            'landscape' => $stats->number_banners_type_landscape,
            'square' => $stats->number_banners_type_square,
            // 'portrait'        => $stats->number_banners_type_portrait,
        ];
    }
}
