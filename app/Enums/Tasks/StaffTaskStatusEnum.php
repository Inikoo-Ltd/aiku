<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Tasks;

use App\Enums\EnumHelperTrait;

enum StaffTaskStatusEnum: string
{
    use EnumHelperTrait;

    case TODO        = 'todo';
    case IN_PROGRESS = 'in_progress';
    case DONE        = 'done';
    case CANCELLED   = 'cancelled';

    public static function labels(): array
    {
        return [
            'todo'        => __('Todo'),
            'in_progress' => __('Working on it'),
            'done'        => __('Done'),
            'cancelled'   => __("Can't be done"),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'todo'        => ['tooltip' => __('Todo'), 'icon' => 'fal fa-circle', 'class' => 'text-gray-500', 'color' => 'gray'],
            'in_progress' => ['tooltip' => __('Working on it'), 'icon' => 'fal fa-spinner', 'class' => 'text-blue-500', 'color' => 'blue'],
            'done'        => ['tooltip' => __('Done'), 'icon' => 'fal fa-check-circle', 'class' => 'text-green-500', 'color' => 'green'],
            'cancelled'   => ['tooltip' => __("Can't be done"), 'icon' => 'fal fa-ban', 'class' => 'text-red-500', 'color' => 'red'],
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::TODO, self::IN_PROGRESS], true);
    }
}
