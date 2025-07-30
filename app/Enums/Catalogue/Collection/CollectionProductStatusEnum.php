<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 01:37:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Collection;

use App\Enums\EnumHelperTrait;

enum CollectionProductStatusEnum: string
{
    use EnumHelperTrait;

    case NORMAL = 'normal';
    case DISCONTINUING = 'discontinuing';
    case DISCONTINUED = 'discontinued';

    public static function labels(): array
    {
        return [
            'normal'        => __('Normal'),
            'discontinuing' => __('Discontinuing'),
            'discontinued'  => __('Discontinued'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'normal'        => [
                'tooltip' => __('normal'),
                'icon'    => 'fas fa-play',
                'class'   => 'text-green-700'
            ],
            'discontinuing' => [
                'tooltip' => __('discontinuing'),
                'icon'    => 'fal fa-sunset',
                'class'   => 'text-amber-500'
            ],
            'discontinued'  => [
                'tooltip' => __('discontinued'),
                'icon'    => 'fal fa-skull',
                'class'   => 'text-red-700'
            ],
        ];
    }

}
