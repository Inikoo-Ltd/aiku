<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 01:37:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\MasterCollection;

use App\Enums\EnumHelperTrait;

enum MasterCollectionStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public static function labels(): array
    {
        return [
            'in_process' => __('In Process'),
            'active'     => __('Active'),
            'inactive'   => __('Inactive'),
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
            'active'     => [
                'tooltip' => __('active'),
                'icon'    => 'fas fa-play',
                'class'   => 'text-green-700'
            ],
            'inactive'   => [
                'tooltip' => __('inactive'),
                'icon'    => 'fal fa-pause-circle',
                'class'   => 'text-gray-500'
            ],
        ];
    }

}
