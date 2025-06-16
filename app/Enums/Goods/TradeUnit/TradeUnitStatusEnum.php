<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 12:07:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\TradeUnit;

use App\Enums\EnumHelperTrait;
use App\Models\SysAdmin\Group;

enum TradeUnitStatusEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case DISCONTINUED = 'discontinued';
    case ANOMALITY = 'anomality';

    public static function labels(): array
    {
        return [
            'in_process'   => __('In process'),
            'active'       => __('Active'),
            'discontinued' => __('Discontinued'),
            'anomality'    => __('Anomality'),
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
            'anomality'      => [
                'tooltip' => __('anomaly'),
                'icon'    => 'fal fa-scarecrow',
                'class'   => 'text-slate-300'
            ],
        ];
    }

    public static function count(Group $group): array
    {
        $stats = $group->goodsStats;

        return [
            'in_process'   => $stats->number_trade_units_status_in_process,
            'active'       => $stats->number_trade_units_status_active,
            'discontinued' => $stats->number_trade_units_status_discontinued,
            'anomality'      => $stats->number_trade_units_status_anomality
        ];
    }

}
