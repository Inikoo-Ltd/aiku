<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 16:26:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Inventory\OrgStock;

use App\Enums\EnumHelperTrait;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;

enum OrgStockStateEnum: string
{
    use EnumHelperTrait;

    case ACTIVE = 'active';
    case DISCONTINUING = 'discontinuing';
    case DISCONTINUED = 'discontinued';
    case SUSPENDED = 'suspended';
    case ABNORMALITY = 'abnormality';

    public static function labels(): array
    {
        return [
            'active' => __('Active'),
            'discontinuing' => __('Discontinuing'),
            'discontinued' => __('Discontinued'),
            'suspended' => __('Suspended'),
            'abnormality' => __('Abnormality'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'active' => [
                'tooltip' => __('active'),
                'icon' => 'fas fa-check-circle',
                'class' => 'text-green-500',
            ],
            'discontinuing' => [
                'tooltip' => __('discontinuing'),
                'icon' => 'fas fa-times-circle',
                'class' => 'text-amber-500',
            ],
            'discontinued' => [
                'tooltip' => __('discontinued'),
                'icon' => 'fas fa-times-circle',
                'class' => 'text-red-500',
            ],
            'suspended' => [
                'tooltip' => __('suspended'),
                'icon' => 'fas fa-pause-circle',
                'class' => 'text-slate-300',
            ],
            'abnormality' => [
                'tooltip' => __('abnormality'),
                'icon' => 'fas fa-exclamation-circle',
                'class' => 'text-red-500',
            ],
        ];
    }

    public static function count(Organisation|OrgStockFamily|OrgAgent|OrgPartner $parent): array
    {
        if ($parent instanceof OrgStockFamily) {
            $stats = $parent->stats;
        } elseif ($parent instanceof OrgPartner) {
            $stats = $parent->partner->inventoryStats;
        } elseif ($parent instanceof OrgAgent) {
            $stats = $parent->agent->organisation->inventoryStats;
        } else {
            $stats = $parent->inventoryStats;
        }

        return [
            'active' => $stats->number_org_stocks_state_active,
            'discontinuing' => $stats->number_org_stocks_state_discontinuing,
            'discontinued' => $stats->number_org_stocks_state_discontinued,
            'suspended' => $stats->number_org_stocks_state_suspended,
            'abnormality' => $stats->number_org_stocks_state_abnormality,
        ];
    }
}
