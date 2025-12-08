<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jul 2023 12:56:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Web\Banner;

use App\Enums\EnumHelperTrait;
use App\Models\Web\Website;

enum BannerStateEnum: string
{
    use EnumHelperTrait;

    case UNPUBLISHED = 'unpublished';
    case LIVE = 'live';
    case SWITCH_OFF = 'switch_off';

    public static function labels(): array
    {
        return [
            'unpublished' => __('Unpublished'),
            'live' => __('Live'),
            'switch_off' => __('Switch off'),

        ];
    }

    public static function stateIcon(): array
    {
        return [
            'unpublished' => [

                'tooltip' => __('unpublished'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-indigo-500',

            ],
            'live' => [

                'tooltip' => __('live'),
                'icon' => 'fal fa-broadcast-tower',
                'class' => 'text-green-600 animate-pulse',

            ],
            'switch_off' => [

                'tooltip' => __('switch off'),
                'icon' => 'fal fa-eye-slash',

            ],

        ];
    }

    public static function dateIcon(): array
    {
        return [
            'unpublished' => [

                'tooltip' => __('created'),
                'icon' => 'fal fa-sparkles',
                'class' => 'text-yellow-500',

            ],
            'live' => [
                'tooltip' => __('published'),
                'icon' => 'fal fa-rocket',

            ],
            'switch_off' => [
                'tooltip' => __('switch off'),
                'icon' => 'fal fa-do-not-enter',
                'class' => 'text-red-500',

            ],

        ];
    }

    public static function count(Website $parent): array
    {
        $stats = $parent->webStats;

        return [
            'unpublished' => $stats->number_banners_state_unpublished,
            'live' => $stats->number_banners_state_live,
            'switch_off' => $stats->number_banners_state_switch_off,
        ];
    }
}
