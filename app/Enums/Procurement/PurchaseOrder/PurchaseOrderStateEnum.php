<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Apr 2023 17:11:07 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\PurchaseOrder;

use App\Enums\EnumHelperTrait;

enum PurchaseOrderStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case SUBMITTED = 'submitted';
    case CONFIRMED = 'confirmed';
    // Final
    case SETTLED = 'settled';
    case CANCELLED = 'cancelled';
    case NOT_RECEIVED = 'not_received';

    public static function labels(): array
    {
        return [
            'in_process'   => __('In process'),
            'submitted'    => __('Submitted'),
            'confirmed'    => __('Accepted'),
            'settled'      => __('Settled'),
            'cancelled'    => __('Cancelled'),
            'not_received' => __('Not Received'),
        ];
    }

    /**
     * The state as the buying organisation reads it: an order through an agent is accepted by the agent.
     */
    public function labelFor(bool $throughAgent): string
    {
        return $throughAgent && $this === self::CONFIRMED ? __('Accepted by agent') : self::labels()[$this->value];
    }

    public function iconFor(bool $throughAgent): array
    {
        return array_merge(self::stateIcon()[$this->value], ['tooltip' => $this->labelFor($throughAgent)]);
    }

    public static function stateIcon(): array
    {
        return [
            'in_process'   => [
                'tooltip' => __('Creating'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',
                'color'   => 'lime',
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5',
                ],
            ],
            'submitted'    => [
                'tooltip' => __('Submitted'),
                'icon'    => 'fal fa-paper-plane',
                'class'   => 'text-indigo-400',
                'color'   => 'indigo',
                'app'     => [
                    'name' => 'share',
                    'type' => 'font-awesome-5',
                ],
            ],
            'confirmed'    => [
                'tooltip' => __('Accepted'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'spell-check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'settled'      => [
                'tooltip' => __('Settled'),
                'icon'    => 'fal fa-check-double',
                'class'   => 'text-success-500',
                'color'   => 'success',
                'app'     => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5',
                ],
            ],
            'cancelled'    => [
                'tooltip' => __('Cancelled'),
                'icon'    => 'fal fa-times-circle',
                'class'   => 'text-red-500',
                'color'   => 'danger',
                'app'     => [
                    'name' => 'times-circle',
                    'type' => 'font-awesome-5',
                ],
            ],
            'not_received' => [
                'tooltip' => __('Not Received'),
                'icon'    => 'fal fa-exclamation-circle',
                'class'   => 'text-warning-500',
                'color'   => 'warning',
                'app'     => [
                    'name' => 'exclamation-circle',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
