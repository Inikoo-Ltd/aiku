<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 12:07:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\Stock;

use App\Enums\EnumHelperTrait;
use App\Models\Goods\StockFamily;
use App\Models\SysAdmin\Group;

enum StockStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case DISCONTINUING = 'discontinuing';
    case DISCONTINUED = 'discontinued';
    case SUSPENDED = 'suspended';

    public static function labels(): array
    {
        return [
            'in_process' => __('In process'),
            'active' => __('Active'),
            'discontinuing' => __('Discontinuing'),
            'discontinued' => __('Discontinued'),
            'suspended' => __('Suspended'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('in process'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-green-500',
            ],
            'active' => [
                'tooltip' => __('Active'),
                'icon' => 'fal fa-check',
                'class' => 'text-green-500',
            ],
            'discontinuing' => [
                'tooltip' => __('discontinuing'),
                'icon' => 'fal fa-exclamation-triangle',
                'class' => 'text-orange-500',
            ],
            'discontinued' => [
                'tooltip' => __('discontinued'),
                'icon' => 'fal fa-skull',
                'class' => 'text-yellow-500',
            ],
            'suspended' => [
                'tooltip' => __('suspended'),
                'icon' => 'fas fa-pause-circle',
                'class' => 'text-slate-300',
            ],
        ];
    }

    public static function count(Group|StockFamily $parent): array
    {
        if ($parent instanceof Group) {
            $stats = $parent->inventoryStats;
        } else {
            $stats = $parent->stats;
        }

        return [
            'in_process' => $stats->number_stocks_state_in_process,
            'active' => $stats->number_stocks_state_active,
            'discontinuing' => $stats->number_stocks_state_discontinuing,
            'discontinued' => $stats->number_stocks_state_discontinued,
            'suspended' => $stats->number_stocks_state_suspended,
        ];
    }
}
