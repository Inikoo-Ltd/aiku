<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 17:08:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\StoredItemAudit;

use App\Enums\EnumHelperTrait;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;

enum StoredItemAuditStateEnum: string
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
                'class' => 'text-yellow-500',  // Color for normal icon (Aiku)
                'color' => 'yellow',  // Color for box (Retina)
                'app' => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5',
                ],
            ],
            'completed' => [
                'tooltip' => __('Completed'),
                'icon' => 'fal fa-check',
                'class' => 'text-lime-500',
                'color' => 'lime',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }

    public static function count(
        Organisation|FulfilmentCustomer|Fulfilment|Warehouse $parent
    ): array {
        if ($parent instanceof FulfilmentCustomer) {
            $stats = $parent;
        } else {
            $stats = $parent->stats;
        }

        return [
            'in_process' => $stats->number_stored_item_audits_state_in_process,
            'completed' => $stats->number_stored_item_audits_state_cmpleted,
        ];
    }

    public static function notifications(string $reference): array
    {
        return [
            'in_process' => [
                'title' => __('Audit :reference created', ['reference' => $reference]),
                'subtitle' => __('Audit (stored items) has been created'),
            ],
            'completed' => [
                'title' => __('Audit :reference completed', ['reference' => $reference]),
                'subtitle' => __('Audit (stored items) has been completed'),
            ],

        ];
    }
}
