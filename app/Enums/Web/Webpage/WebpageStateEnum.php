<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 16 Jan 2024 11:39:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Web\Webpage;

use App\Enums\EnumHelperTrait;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;

enum WebpageStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case READY      = 'ready';
    case LIVE   = 'live';
    case CLOSED = 'closed';


    public static function labels(): array
    {
        return [
            'in_process' => __('In construction'),
            'ready'      => __('Ready'),
            'live'       => __('Live'),
            'closed'     => __('Closed'),
        ];
    }

    public static function count(Website|Webpage|Organisation $parent): array
    {
        $webStats = match (class_basename($parent)) {
            'Organisation','Website' => $parent->webStats,
            'Webpage'                => $parent->stats
        };


        return [
            'in_process' => $webStats->number_webpages_state_in_process,
            'ready'      => $webStats->number_webpages_state_ready,
            'live'       => $webStats->number_webpages_state_live,
            'closed'     => $webStats->number_webpages_state_closed,
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('In construction'),
                'icon'    => 'fal fa-hammer',
                'class'   => 'text-amber-500'
            ],
            'ready' => [
                'tooltip' => __('Ready'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-blue-500'
            ],
            'live' => [
                'tooltip' => __('Live'),
                'icon'    => 'fal fa-broadcast-tower',
                'class'   => 'text-green-600 animate-pulse'
            ],
            'closed' => [
                'tooltip' => __('Closed'),
                'icon'    => 'fal fa-skull',
                'class'   => 'text-gray-500'
            ],
        ];
    }


}
