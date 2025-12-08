<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 12:07:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\StockFamily;

use App\Enums\EnumHelperTrait;
use App\Models\SysAdmin\Group;

enum StockFamilyStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case DISCONTINUING = 'discontinuing';
    case DISCONTINUED = 'discontinued';

    public static function labels(): array
    {
        return [
            'in_process' => __('In process'),
            'active' => __('Active'),
            'discontinuing' => __('Discontinuing'),
            'discontinued' => __('Discontinued'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('in process'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-indigo-500',
            ],
            'active' => [
                'tooltip' => __('contacted'),
                'icon' => 'fal fa-chair',
                'class' => 'text-green-500',
            ],
            'discontinuing' => [
                'tooltip' => __('discontinuing'),
                'icon' => 'fal fa-exclamation-triangle',
                'class' => 'text-orange-500',
            ],
            'discontinued' => [
                'tooltip' => __('discontinued'),
                'icon' => 'fal fa-laugh',
                'class' => 'text-red-500',
            ],
        ];
    }

    public static function count(Group $parent): array
    {
        $stats = $parent->inventoryStats;

        return [
            'in_process' => $stats->number_stock_families_state_in_process,
            'active' => $stats->number_stock_families_state_active,
            'discontinuing' => $stats->number_stock_families_state_discontinuing,
            'discontinued' => $stats->number_stock_families_state_discontinued,
        ];
    }
}
