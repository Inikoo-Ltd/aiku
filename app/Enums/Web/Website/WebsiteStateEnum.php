<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 00:47:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Web\Website;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum WebsiteStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case LIVE = 'live';
    case CLOSED = 'closed';

    public static function labels(): array
    {
        return [
            'in_process' => __('In construction'),
            'live' => __('Live'),
            'closed' => __('Closed'),
        ];
    }

    public static function count(Group|Organisation|Shop|Fulfilment $parent): array
    {
        if ($parent instanceof Group || $parent instanceof Organisation) {
            $webStats = $parent->webStats;

            return [
                'in_process' => $webStats->number_websites_state_in_process,
                'live' => $webStats->number_websites_state_live,
                'closed' => $webStats->number_websites_state_closed,
            ];
        } elseif ($parent instanceof Shop) {
            return [
                'in_process' => $parent->website()->where('state', self::IN_PROCESS)->count(),
                'live' => $parent->website()->where('state', self::LIVE)->count(),
                'closed' => $parent->website()->where('state', self::CLOSED)->count(),
            ];
        }

        return [
            'in_process' => $parent->shop->website()->where('state', self::IN_PROCESS)->count(),
            'live' => $parent->shop->website()->where('state', self::LIVE)->count(),
            'closed' => $parent->shop->website()->where('state', self::CLOSED)->count(),
        ];

    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [

                'tooltip' => __('in process'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-emerald-500',

            ],
            'live' => [

                'tooltip' => __('live'),
                'icon' => 'fal fa-broadcast-tower',
                'class' => 'text-green-600 animate-pulse',

            ],
            'closed' => [

                'tooltip' => __('closed'),
                'icon' => 'fal fa-skull',

            ],

        ];
    }
}
