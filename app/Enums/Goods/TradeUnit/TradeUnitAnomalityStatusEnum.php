<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 10:54:51 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\TradeUnit;

use App\Enums\EnumHelperTrait;
use App\Models\SysAdmin\Group;

enum TradeUnitAnomalityStatusEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case DISCONTINUED = 'discontinued';

    public static function labels(): array
    {
        return [
            'in_process'   => __('In process'),
            'active'       => __('Active'),
            'discontinued' => __('Discontinued'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process'   => [
                'tooltip' => __('in process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-indigo-500'
            ],
            'active'       => [
                'tooltip' => __('contacted'),
                'icon'    => 'fal fa-chair',
                'class'   => 'text-green-500'
            ],
            'discontinued' => [
                'tooltip' => __('discontinued'),
                'icon'    => 'fal fa-laugh',
                'class'   => 'text-red-500'
            ],
        ];
    }

    public static function count(Group $group): array
    {
        $stats = $group->goodsStats;

        return [
            'in_process'   => $stats->number_trade_units_anomality_status_in_process,
            'active'       => $stats->number_trade_units_anomality_status_active,
            'discontinued' => $stats->number_trade_units_anomality_status_discontinued,
        ];
    }
}
