<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:17:42 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Dispatch;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithIndicator;
use App\Models\Dispatching\DeliveryNote;
use App\Models\GoodsIn\ReturnDeliveryNote;

enum DeliveryNoteTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithIndicator;

    case ITEMS = 'items';
    case PICKING_TODO_ITEMS = 'picking_todo_items';
    case PICKING_DONE_ITEMS = 'picking_done_items';
    case PENDING_ITEMS = 'pending_items';
    case DONE_ITEMS = 'done_items';
    case TARIFF_CODES = 'tariff_codes';
    case HISTORY = 'history';
    // case PICKINGS = 'pickings';


    public function blueprint(DeliveryNote|ReturnDeliveryNote $parent): array
    {

        return match ($this) {
            DeliveryNoteTabsEnum::ITEMS => [
                'title' => __('Items'),
                'icon'  => 'fal fa-bars',
            ],

            DeliveryNoteTabsEnum::PICKING_TODO_ITEMS => [
                'title'       => __('Todo items'),
                'icon'        => 'fal fa-clipboard-list-check',
                'colorScheme' => 'amber',
            ],

            DeliveryNoteTabsEnum::PICKING_DONE_ITEMS => [
                'title'       => __('Done items'),
                'icon'        => 'fal fa-clipboard-check',
                'colorScheme' => 'green',
            ],

            DeliveryNoteTabsEnum::PENDING_ITEMS => [
                'title'       => __('Todo items'),
                'icon'        => 'fal fa-clipboard-list-check',
                'colorScheme' => 'amber',
            ],

            DeliveryNoteTabsEnum::DONE_ITEMS => [
                'title'       => __('Done items'),
                'icon'        => 'fal fa-clipboard-check',
                'colorScheme' => 'green',
            ],
            DeliveryNoteTabsEnum::TARIFF_CODES => [
                'title' => __('Tariff codes / Origin'),
                'icon'  => 'fal fa-globe',
            ],
            // DeliveryNoteTabsEnum::PICKINGS => [
            //     'title'     => __('pickings'),
            //     'icon'      => 'fal fa-box-full',
            //     'type'      => 'icon',
            //     'align'     => 'right',
            //     'indicator' => $indicator
            // ],
            DeliveryNoteTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
