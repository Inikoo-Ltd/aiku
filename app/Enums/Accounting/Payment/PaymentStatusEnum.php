<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 02:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\Payment;

use App\Enums\EnumHelperTrait;

enum PaymentStatusEnum: string
{
    use EnumHelperTrait;
    case IN_PROCESS = 'in_process';
    case SUCCESS = 'success';
    case FAIL = 'fail';

    public static function stateIcon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('In process'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-lime-500',
                'color' => 'lime',
                'app' => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5',
                ],
            ],
            'success' => [
                'tooltip' => __('Success'),
                'icon' => 'fal fa-check',
                'class' => 'text-indigo-400',
                'color' => 'indigo',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'fail' => [
                'tooltip' => __('Fail'),
                'icon' => 'fal fa-times',
                'class' => 'text-emerald-500',
                'color' => 'emerald',
                'app' => [
                    'name' => 'times',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
