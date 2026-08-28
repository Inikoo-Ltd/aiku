<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\ShoppingListItem;

use App\Enums\EnumHelperTrait;

enum ShoppingListItemPriorityEnum: string
{
    use EnumHelperTrait;

    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public static function labels(): array
    {
        return [
            'low'    => __('Low'),
            'normal' => __('Normal'),
            'high'   => __('High'),
            'urgent' => __('Urgent'),
        ];
    }
}
