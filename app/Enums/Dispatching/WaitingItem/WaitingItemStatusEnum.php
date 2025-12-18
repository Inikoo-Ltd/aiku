<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Dec 2025 12:20:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\WaitingItem;

use App\Enums\EnumHelperTrait;

enum WaitingItemStatusEnum: string
{
    use EnumHelperTrait;


    case TO_DO = 'to_do';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';


    public static function labels(): array
    {
        return [
            'to_do'       => __('To Do'),
            'in_progress' => __('In Progress'),
            'done'        => __('Done'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'to_do'       => [
                'tooltip' => __('To Do'),
                'icon'    => 'fal fa-inbox',
                'class'   => 'text-grey-500',
            ],
            'in_progress' => [
                'tooltip' => __('In Progress'),
                'icon'    => 'fal fa-wrench',
                'class'   => 'text-blue-500',
            ],
            'done'        => [
                'tooltip' => __('Done'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-500',

            ],


        ];
    }
}
