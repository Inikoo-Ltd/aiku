<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 15:20:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Inventory\OrgStockFamily;

use App\Enums\EnumHelperTrait;
use App\Models\SysAdmin\Organisation;

enum OrgStockFamilyStateEnum: string
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
                'class' => 'text-lime-500',
                'color' => 'lime',
                'app' => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5',
                ],
            ],
            'active' => [
                'tooltip' => __('Active'),
                'icon' => 'fal fa-spell-check',
                'class' => 'text-emerald-500',
                'color' => 'emerald',
                'app' => [
                    'name' => 'spell-check',
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

    public static function count(Organisation $organisation): array
    {
        $stats = $organisation->inventoryStats;

        return [
            'in_process' => $stats->number_org_stock_families_state_in_process,
            'active' => $stats->number_org_stock_families_state_active,
            'discontinuing' => $stats->number_org_stock_families_state_discontinuing,
            'discontinued' => $stats->number_org_stock_families_state_discontinued,
        ];
    }
}
