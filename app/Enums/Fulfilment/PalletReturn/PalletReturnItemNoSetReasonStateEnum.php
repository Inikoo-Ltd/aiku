<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jan 2024 15:25:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\PalletReturn;

use App\Enums\EnumHelperTrait;

enum PalletReturnItemNoSetReasonStateEnum: string
{
    use EnumHelperTrait;

    case OTHER = 'other';
    case UNKNOWN = 'unknown';
    case NOT_SETUP = 'not_setup';
    case AUTO_STOCK = 'auto_stock';

    public static function labels(): array
    {
        return [
            'in_process' => __('Other'),
            'submitted' => __('Unknown'),
            'confirmed' => __('Not Setup'),
            'picking' => __('Auto Stock'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'other' => [
                'tooltip' => __('Other'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-lime-500',
                'color' => 'lime',
            ],
            'unknown' => [
                'tooltip' => __('Unknown'),
                'icon' => 'fal fa-share',
                'class' => 'text-indigo-400',
                'color' => 'indigo',
            ],
            'not_setup' => [
                'tooltip' => __('Not Setup'),
                'icon' => 'fal fa-spell-check',
                'class' => 'text-emerald-500',
                'color' => 'emerald',
            ],
            'auto_stock' => [
                'tooltip' => __('Auto Stock'),
                'icon' => 'fal fa-truck',
                'class' => 'text-orange-500',
                'color' => 'orange',
            ],
        ];
    }
}
