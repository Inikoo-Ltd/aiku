<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 16:23:30 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain\SupplierProduct;

use App\Enums\EnumHelperTrait;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;

enum SupplierProductStateEnum: string
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
                'tooltip' => __('in process'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-indigo-500',
            ],
            'active' => [
                'tooltip' => __('contacted'),
                'icon' => 'fal fa-chair',
                'class' => 'text-green-500',
            ],
            'discontinuing' => [
                'tooltip' => __('discontinuing'),
                'icon' => 'fal fa-exclamation-triangle',
                'class' => 'text-orange-500',
            ],
            'discontinued' => [
                'tooltip' => __('discontinued'),
                'icon' => 'fal fa-laugh',
                'class' => 'text-red-500',
            ],
        ];
    }

    public static function count(Group|Agent|Supplier $parent): array
    {
        if ($parent instanceof Group) {
            $stats = $parent->supplyChainStats;
        } else {
            $stats = $parent->stats;
        }

        return [
            'in_process' => $stats->number_supplier_products_state_in_process,
            'active' => $stats->number_supplier_products_state_active,
            'discontinuing' => $stats->number_supplier_products_state_discontinuing,
            'discontinued' => $stats->number_supplier_products_state_discontinued,
        ];
    }
}
