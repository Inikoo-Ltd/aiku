<?php

namespace App\Enums\Production\JobOrderItem;

use App\Enums\EnumHelperTrait;

enum JobOrderItemStatusEnum: string
{
    use EnumHelperTrait;

    case RECEIVING = 'receiving';
    case NOT_RECEIVED = 'not_received';
    case STORING = 'storing';
    case RETURNING = 'returning';
    case RETURNED = 'returned';
    case INCIDENT = 'incident';

    public static function labels($forElements = false): array
    {
        $labels = [
            'receiving' => __('Receiving'),
            'not_received' => __('Not received'),
            'storing' => __('Storing'),
            'returning' => __('Returning'),
            'returned' => __('Returned'),
            'incident' => __('Incidents'),

        ];

        return $labels;
    }

    public static function statusIcon(): array
    {
        return [
            'receiving' => [
                'tooltip' => __('In process'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-lime-500',  // Color for normal icon (Aiku)
                'color' => 'lime',  // Color for box (Retina)
                'app' => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5',
                ],
            ],
            'not_received' => [
                'tooltip' => __('not received'),
                'icon' => 'fal fa-times',
                'class' => 'text-red-500',
                'color' => 'red',
                'app' => [
                    'name' => 'times',
                    'type' => 'font-awesome-5',
                ],
            ],
            'storing' => [
                'tooltip' => __('Storing'),
                'icon' => 'fal fa-check-double',
                'class' => 'text-purple-500',
                'color' => 'purple',
                'app' => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5',
                ],
            ],
            'incident' => [
                'tooltip' => __('Incident'),
                'icon' => 'fal fa-sad-cry',
                'class' => 'text-red-600',
                'color' => 'gray',
                'app' => [
                    'name' => 'sad-cry',
                    'type' => 'font-awesome-5',
                ],
            ],
            'returning' => [
                'tooltip' => __('Returning'),
                'icon' => 'fal fa-check',
                'class' => 'text-green-400',
                'color' => 'green',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'returned' => [
                'tooltip' => __('Returned'),
                'icon' => 'fal fa-check',
                'class' => 'text-green-400',
                'color' => 'green',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
