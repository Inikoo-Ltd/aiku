<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Jun 2023 08:28:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Task;

use App\Enums\EnumHelperTrait;

enum TaskStatusEnum: string
{
    use EnumHelperTrait;

    case COMPLETED = 'completed';
    case PENDING = 'pending';
    case FAILED = 'failed';

    public static function labels(): array
    {
        return [
            'completed' => __('Completed'),
            'pending' => __('Pending'),
            'failed' => __('Failed'),
        ];
    }
}
