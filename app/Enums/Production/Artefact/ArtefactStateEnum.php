<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 11:28:50 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Production\Artefact;

use App\Enums\EnumHelperTrait;
use App\Models\SysAdmin\Group;

enum ArtefactStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case DISCONTINUED = 'discontinued';

    public static function labels(): array
    {
        return [
            'in_process' => __('In process'),
            'active' => __('Active'),
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
            'in_process' => $stats->number_stocks_state_in_process,
            'active' => $stats->number_stocks_state_active,
            'discontinued' => $stats->number_stocks_state_discontinued,
        ];
    }
}
