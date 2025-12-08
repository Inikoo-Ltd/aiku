<?php

/*
 * author Arya Permana - Kirin
 * created on 20-02-2025-16h-43m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Fulfilment\StoredItemAudit;

use App\Enums\EnumHelperTrait;
use App\Models\Fulfilment\Fulfilment;

enum StoredItemAuditScopeEnum: string
{
    use EnumHelperTrait;

    case FULFILMENT = 'Fulfilment';
    case PALLET = 'Pallet';

    public static function labels(): array
    {
        return [
            'fulfilment' => __('Fulfilment'),
            'pallet' => __('Pallet'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'fulfilment' => [
                'tooltip' => __('Fulfilment'),
                'icon' => 'fal fa-hand-holding-box',
                'class' => 'text-yellow-500',  // Color for normal icon (Aiku)
                'color' => 'yellow',  // Color for box (Retina)
                'app' => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5',
                ],
            ],
            'pallet' => [
                'tooltip' => __('Pallet'),
                'icon' => 'fal fa-pallet',
                'class' => 'text-lime-500',
                'color' => 'lime',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
