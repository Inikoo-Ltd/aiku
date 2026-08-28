<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Production\JobOrderItemTask;

use App\Enums\EnumHelperTrait;

enum JobOrderItemTaskStateEnum: string
{
    use EnumHelperTrait;

    case TODO        = 'todo';
    case IN_PROGRESS = 'in_progress';
    case DONE        = 'done';

    public static function labels($forElements = false): array
    {
        return [
            'todo'        => __('To Do'),
            'in_progress' => __('In Progress'),
            'done'        => __('Done'),
        ];
    }
}
