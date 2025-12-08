<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 09 May 2023 13:09:10 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\PurchaseOrderTransaction;

use App\Enums\EnumHelperTrait;

enum PurchaseOrderTransactionStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case SUBMITTED = 'submitted';
    case CONFIRMED = 'confirmed';
    case SETTLED = 'settled';
    case CANCELLED = 'cancelled';
    case NOT_RECEIVED = 'not_received';

    public static function labels(): array
    {
        return [
            'in_process' => __('In process'),
            'submitted' => __('Submitted'),
            'confirmed' => __('Confirmed'),
            'settled' => __('Settled'),
            'cancelled' => __('Cancelled'),
            'not_received' => __('Not Received'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('Creating'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-lime-500',  // Color for normal icon (Aiku)
                'color' => 'lime',  // Color for box (Retina)
                'app' => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5',
                ],
            ],
            'submitted' => [
                'tooltip' => __('Submitted'),
                'icon' => 'fal fa-paper-plane',
                'class' => 'text-indigo-400',
                'color' => 'indigo',
                'app' => [
                    'name' => 'share',
                    'type' => 'font-awesome-5',
                ],
            ],
            'confirmed' => [
                'tooltip' => __('Confirmed'),
                'icon' => 'fal fa-check-circle',
                'class' => 'text-gray-500',
                'color' => 'gray',
                'app' => [
                    'name' => 'spell-check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'settled' => [
                'tooltip' => __('Settled'),
                'icon' => 'fal fa-check-double',
                'class' => 'text-success-500',
                'color' => 'success',
                'app' => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5',
                ],
            ],
            'cancelled' => [
                'tooltip' => __('Cancelled'),
                'icon' => 'fal fa-times-circle',
                'class' => 'text-danger-500',
                'color' => 'danger',
                'app' => [
                    'name' => 'times-circle',
                    'type' => 'font-awesome-5',
                ],

            ],
            'not_received' => [
                'tooltip' => __('Not Received'),
                'icon' => 'fal fa-exclamation-circle',
                'class' => 'text-warning-500',
                'color' => 'warning',
                'app' => [
                    'name' => 'exclamation-circle',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
