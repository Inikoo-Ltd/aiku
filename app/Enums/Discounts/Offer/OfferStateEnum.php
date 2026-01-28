<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 19:51:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Discounts\Offer;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Group;

enum OfferStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case FINISHED = 'finished';
    case SUSPENDED = 'suspended';


    public static function labels(): array
    {
        return [
            self::IN_PROCESS->value => __('In process'),
            self::ACTIVE->value  => __('Active'),
            self::FINISHED->value => __('Finished'),
            self::SUSPENDED->value  => __('Suspended'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            self::IN_PROCESS->value         => [
                'tooltip' => self::labels()[self::IN_PROCESS->value],
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',  // Color for normal icon (Aiku)
                'color'   => 'lime',  // Color for box (Retina)
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]
            ],
            self::ACTIVE->value         => [
                'tooltip' => self::labels()[self::ACTIVE->value],
                'icon'    => 'fal fa-broadcast-tower',
                'class'   => 'text-green-600 animate-pulse'
            ],
            self::FINISHED->value         => [
                'tooltip' => self::labels()[self::FINISHED->value],
                'icon'    => 'fal fa-skull',
                'class'   => 'text-gray-500',
            ],
            self::SUSPENDED->value         => [
                'tooltip' => self::labels()[self::SUSPENDED->value],
                'icon'    => 'fal fa-ban',
                'class'   => 'text-red-500',
                'color'   => 'slate',
                'app'     => [
                    'name' => 'ban',
                    'type' => 'font-awesome-5'
                ]
            ]
        ];
    }

    // public static function count(Group|Shop|OfferCampaign $parent): array
    // {
    //     return [
    //         'in_process' => 0,
    //         'active'     => 0,
    //         'finished'   => 0,
    //         'suspended'  => 0,
    //     ];
    // }
}
