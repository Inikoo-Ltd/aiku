<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 01:42:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Asset;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;

enum AssetStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case DISCONTINUING = 'discontinuing';
    case DISCONTINUED = 'discontinued';

    public static function labels(): array
    {
        return [
            'in_process' => __('In Process'),
            'active' => __('Active'),
            'discontinuing' => __('Discontinuing'),
            'discontinued' => __('Discontinued'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('In process'),
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
                'icon' => 'fal fa-check',
                'class' => 'text-green-500',
                'color' => 'green',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'discontinuing' => [
                'tooltip' => __('Discontinuing'),
                'icon' => 'fal fa-exclamation-triangle',
                'class' => 'text-orange-500',
                'color' => 'orange',
                'app' => [
                    'name' => 'exclamation-triangle',
                    'type' => 'font-awesome-5',
                ],
            ],
            'discontinued' => [
                'tooltip' => __('Discontinued'),
                'icon' => 'fal fa-times',
                'class' => 'text-red-500',
                'color' => 'red',
                'app' => [
                    'name' => 'times',
                ],
            ],
        ];
    }

    public static function count(Shop|Organisation|ProductCategory|Collection $parent): array
    {
        $stats = $parent->stats;

        return [
            'in_process' => $stats->number_products_state_in_process,
            'active' => $stats->number_products_state_active,
            'discontinuing' => $stats->number_products_state_discontinuing,
            'discontinued' => $stats->number_products_state_discontinued,
        ];
    }
}
