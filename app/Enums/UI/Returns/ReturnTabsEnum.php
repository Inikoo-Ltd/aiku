<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Tabs enum for the Returns UI
 */

namespace App\Enums\UI\Returns;

use App\Enums\EnumHelperTrait;
use App\Models\GoodsIn\OrderReturn;

enum ReturnTabsEnum: string
{
    use EnumHelperTrait;

    case ITEMS = 'items';
    case HISTORY = 'history';

    public static function navigation(OrderReturn $return): array
    {
        return [
            self::ITEMS->value => [
                'label'   => __('Items'),
                'icon'    => 'fal fa-boxes',
                'iconFilled' => 'fas fa-boxes',
                'tooltip' => __('Return Items'),
                'number' => $return->number_items,
            ],
            self::HISTORY->value => [
                'label'   => __('History'),
                'icon'    => 'fal fa-clock',
                'iconFilled' => 'fas fa-clock',
                'tooltip' => __('Return History'),
            ],
        ];
    }
}
