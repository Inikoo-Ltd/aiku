<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 11:54:59 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Inventory\OrgStockAudit;

use App\Enums\EnumHelperTrait;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;

enum OrgStockAuditStateEnum: string
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

    public static function count(
        Organisation|Warehouse $parent
    ): array {

        $stats = $parent->stats;

        return [
            'in_process' => $stats->number_org_stock_audits_state_in_process,
            'completed' => $stats->number_org_stock_audits_state_cmpleted,
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
