<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:22:44 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Customer;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Shop;

enum CustomerStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case REGISTERED = 'registered';
    case ACTIVE = 'active';
    case LOSING = 'losing';
    case LOST = 'lost';

    public static function labels(): array
    {
        return [
            'in_process' => __('In Process'),
            'registered' => __('Registered'),
            'active' => __('Active'),
            'losing' => __('Potential Comebacks'),
            'lost' => __('Dormant'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('In process'),
                'icon' => 'fal fa-circle-notch',
                'class' => 'text-lime-500',
                'color' => 'lime',
            ],
            'registered' => [
                'tooltip' => __('Registered'),
                'icon' => 'fas fa-exclamation-circle',
                'class' => 'text-orange-500',
                'color' => 'orange',
            ],
            'active' => [
                'tooltip' => __('Active'),
                'icon' => 'fas fa-circle',
                'class' => 'text-emerald-500',
                'color' => 'emerald',
            ],
            'losing' => [
                'tooltip' => __('Potential Comebacks'),
                'icon' => 'fas fa-circle',
                'class' => 'text-orange-500',
                'color' => 'orange',
            ],
            'lost' => [
                'tooltip' => __('Dormant'),
                'icon' => 'fas fa-circle',
                'class' => 'text-red-500',
                'color' => 'red',
            ],
        ];
    }

    public static function count(Shop $parent): array
    {
        $stats = $parent->crmStats;

        return [
            'in_process' => $stats->number_customers_state_in_process,
            'registered' => $stats->number_customers_state_registered,
            'active' => $stats->number_customers_state_active,
            'losing' => $stats->number_customers_state_losing,
            'lost' => $stats->number_customers_state_lost,
        ];
    }
}
