<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 16 Apr 2024 12:45:19 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Discounts\OfferCampaign;

use App\Enums\EnumHelperTrait;

enum OfferCampaignStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case FINISHED = 'finished';
    case SUSPENDED = 'suspended';

    public static function labels(): array
    {
        return [
            'in_process' => __('In process'),
            'active' => __('Active'),
            'finished' => __('Finished'),
            'suspended' => __('Suspended'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('In Process'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-lime-500',  // Color for normal icon (Aiku)
                'color' => 'lime',  // Color for box (Retina)
                'app' => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5',
                ],
            ],
            'active' => [
                'tooltip' => __('Active'),
                'icon' => 'fal fa-share',
                'class' => 'text-indigo-400',
                'color' => 'indigo',
                'app' => [
                    'name' => 'share',
                    'type' => 'font-awesome-5',
                ],
            ],
            'finished' => [
                'tooltip' => __('Finished'),
                'icon' => 'fal fa-spell-check',
                'class' => 'text-emerald-500',
                'color' => 'emerald',
                'app' => [
                    'name' => 'spell-check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'suspended' => [
                'tooltip' => __('Suspended'),
                'icon' => 'fal fa-pause',
                'class' => 'text-red-500',
                'color' => 'red',
                'app' => [
                    'name' => 'pause',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
