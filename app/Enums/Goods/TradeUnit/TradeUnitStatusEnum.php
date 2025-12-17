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
    case DISCONTINUING = 'discontinuing';
    case DISCONTINUED = 'discontinued';
    case ANOMALITY = 'anomality';

    public static function labels(): array
    {
        return [
            'in_process'    => __('In process'),
            'active'        => __('Active'),
            'discontinuing' => __('Discontinuing'),
            'discontinued'  => __('Discontinued'),
            'anomality'     => __('Anomality'),
        ];
    }

    public static function icon(): array
    {
        return [
            'in_process'    => [
                'tooltip' => __('In process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-indigo-500'
            ],
            'active'        => [
                'tooltip' => __('Active'),
                'icon'    => 'fas fa-check-circle',
                'class'   => 'text-green-500'
            ],
            'discontinuing' => [
                'tooltip' => __('Discontinuing'),
                'icon'    => 'fal fa-exclamation-triangle',
                'class'   => 'text-orange-500'
            ],
            'discontinued'  => [
                'tooltip' => __('Discontinued'),
                'icon'    => 'fas fa-skull',
                'class'   => 'text-yellow-500'
            ],
            'anomality'     => [
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
            'in_process'    => $stats->number_trade_units_status_in_process,
            'active'        => $stats->number_trade_units_status_active,
            'discontinuing' => $stats->number_trade_units_status_discontinuing,
            'discontinued'  => $stats->number_trade_units_status_discontinued,
            'anomality'     => $stats->number_trade_units_status_anomality
        ];
    }

}
