<?php

/*
 * author Arya Permana - Kirin
 * created on 28-10-2024-10h-42m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Enums\Accounting\InvoiceCategory;

use App\Enums\EnumHelperTrait;

enum InvoiceCategoryStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case CLOSED = 'closed';
    case COOLDOWN = 'cooldown';

    public static function labels(): array
    {
        return [
            'in_process' => __('in process'),
            'active'     => __('active'),
            'closed'     => __('closed'),
            'cooldown'   => __('cooldown'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('In process'),
                'label'  => __('In process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',
                'color'   => 'lime',
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]
            ],
            'active' => [
                'tooltip' => __('Active'),
                'label' => __('Active'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-green-500',
                'color'   => 'green',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'closed' => [
                'tooltip' => __('Closed'),
                'label' => __('Closed'),
                'icon'    => 'fal fa-times-circle',
                'class'   => 'text-red-500',
                'color'   => 'red',
                'app'     => [
                    'name' => 'times-circle',
                    'type' => 'font-awesome-5'
                ]
            ],
            'cooldown' => [
                'tooltip' => __('Cooldown'),
                'label' => __('Cooldown'),
                'icon'    => 'fal fa-thermometer-empty',
                'class'   => 'text-blue-500',
                'color'   => 'blue',
                'app'     => [
                    'name' => 'thermometer-empty',
                    'type' => 'font-awesome-5'
                ]
            ],
        ];
    }
}
