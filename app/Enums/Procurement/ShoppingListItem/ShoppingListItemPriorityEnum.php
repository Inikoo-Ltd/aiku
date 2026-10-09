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

    /**
     * @return array<string, array{icon: string, class: string, tooltip: string}>
     */
    public static function icons(): array
    {
        return [
            'low'    => ['icon' => 'fal fa-chevron-double-down', 'class' => 'text-gray-400', 'tooltip' => __('Low')],
            'normal' => ['icon' => 'fal fa-equals', 'class' => 'text-emerald-600', 'tooltip' => __('Normal')],
            'high'   => ['icon' => 'fal fa-chevron-double-up', 'class' => 'text-amber-600', 'tooltip' => __('High')],
            'urgent' => ['icon' => 'fal fa-exclamation-triangle', 'class' => 'text-red-600', 'tooltip' => __('Urgent')],
        ];
    }
}
