<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 01:37:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Collection;

use App\Enums\EnumHelperTrait;

enum CollectionStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DISCONTINUING = 'discontinuing';
    case DISCONTINUED = 'discontinued';

    public static function labels(): array
    {
        return [
            'in_process'    => __('In Process'),
            'active'        => __('Active'),
            'inactive'      => __('Inactive'),
            'discontinuing' => __('Discontinuing'),
            'discontinued'  => __('Discontinued'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('in process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-gray-400'
            ],
            'active' => [
                'tooltip' => __('active'),
                'icon'    => 'fas fa-play',
                'class'   => 'text-green-700'
            ],
            'inactive' => [
                'tooltip' => __('inactive'),
                'icon'    => 'fal fa-pause-circle',
                'class'   => 'text-gray-500'
            ],
            'discontinuing' => [
                'tooltip' => __('discontinuing'),
                'icon'    => 'fal fa-sunset',
                'class'   => 'text-amber-500'
            ],
            'discontinued' => [
                'tooltip' => __('discontinued'),
                'icon'    => 'fal fa-skull',
                'class'   => 'text-red-700'
            ],
        ];
    }

}
