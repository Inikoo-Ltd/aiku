<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\RecurringBill;

use App\Enums\EnumHelperTrait;

enum RecurringBillStatusEnum: string
{
    use EnumHelperTrait;

    case CURRENT = 'current';
    case FORMER = 'former';

    public static function labels(): array
    {
        return [
            'current' => __('Current bill'),
            'former' => __('Previous bill'),

        ];
    }

    public static function statusIcon(): array
    {
        return [
            'current' => [
                'tooltip' => __('Current'),
                'icon' => 'fal fa-check-circle',
                'class' => 'text-green-500',
                'color' => 'green',
                'app' => [
                    'name' => 'check-circle',
                    'type' => 'font-awesome-5',
                ],
            ],
            'former' => [
                'tooltip' => __('Former'),
                'icon' => 'fal fa-times-circle',
                'class' => 'text-red-500',
                'color' => 'red',
                'app' => [
                    'name' => 'times-circle',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
