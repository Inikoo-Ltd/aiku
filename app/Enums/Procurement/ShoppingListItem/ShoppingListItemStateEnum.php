<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\ShoppingListItem;

use App\Enums\EnumHelperTrait;

enum ShoppingListItemStateEnum: string
{
    use EnumHelperTrait;

    case OPEN = 'open';
    case DISMISS_PROPOSED = 'dismiss_proposed';
    case ORDERED = 'ordered';
    case DISMISSED = 'dismissed';

    public static function labels(): array
    {
        return [
            'open'             => __('Open'),
            'dismiss_proposed' => __('Dismissal proposed'),
            'ordered'          => __('Ordered'),
            'dismissed'        => __('Dismissed'),
        ];
    }
}
