<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 16:26:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Inventory\OrgStock;

use App\Enums\EnumHelperTrait;
use App\Models\SysAdmin\Organisation;

enum OrgStockStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS        = 'in-process';
    case ACTIVE            = 'active';
    case DISCONTINUING     = 'discontinuing';
    case DISCONTINUED      = 'discontinued';

    public static function labels(): array
    {
        return [
            'in-process'    => __('In process'),
            'active'        => __('Active'),
            'discontinuing' => __('Discontinuing'),
            'discontinued'  => __('Discontinued'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in-process' => [
                'tooltip' => __('in process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-indigo-500'
            ],
            'active'    => [
                'tooltip' => __('contacted'),
                'icon'    => 'fal fa-chair',
                'class'   => 'text-green-500'
            ],
            'discontinuing'         => [
                'tooltip' => __('discontinuing'),
                'icon'    => 'fal fa-thumbs-down',
                'class'   => 'text-gray-500'
            ],
            'discontinued'      => [
                'tooltip' => __('discontinued'),
                'icon'    => 'fal fa-laugh',
                'class'   => 'text-red-500'
            ],
        ];
    }

    public static function count(Organisation $parent): array
    {
        $stats = $parent->inventoryStats;

        return [
            'in-process'        => $stats->number_stocks_state_in_process,
            'active'            => $stats->number_stocks_state_active,
            'discontinuing'     => $stats->number_stocks_state_discontinuing,
            'discontinued'      => $stats->number_stocks_state_discontinued,
        ];
    }
}
