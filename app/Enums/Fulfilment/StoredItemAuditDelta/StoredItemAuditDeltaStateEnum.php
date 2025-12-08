<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 17:08:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\StoredItemAuditDelta;

use App\Enums\EnumHelperTrait;

enum StoredItemAuditDeltaStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case COMPLETED = 'completed';

    public static function labels(): array
    {
        return [
            'in_process' => __('In Process'),
            'completed' => __('Completed'),
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
            'completed' => [
                'tooltip' => __('Completed'),
                'icon' => 'fal fa-check',
                'class' => 'text-indigo-400',
                'color' => 'indigo',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
