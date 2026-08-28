<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 May 2024 13:07:55 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum StockDeliveryTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ITEMS                = 'items';
    case PENDING_ITEMS        = 'pending_items';
    case DONE_ITEMS           = 'done_items';
    case UNDER_OVER_DELIVERED = 'under_over_delivered';
    case SHOWCASE             = 'showcase';
    case ATTACHMENTS          = 'attachments';
    case HISTORY              = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            StockDeliveryTabsEnum::ITEMS                => [
                'title' => __('Items'),
                'icon'  => 'fal fa-bars',
            ],
            StockDeliveryTabsEnum::PENDING_ITEMS        => [
                'title' => __('Pending Items'),
                'icon'  => 'fal fa-clipboard-list-check',
            ],
            StockDeliveryTabsEnum::DONE_ITEMS           => [
                'title' => __('Done Items'),
                'icon'  => 'fal fa-clipboard-check',
            ],
            StockDeliveryTabsEnum::UNDER_OVER_DELIVERED => [
                'title' => __('Under/Over delivered items'),
                'icon'  => 'fal fa-box-open',
            ],
            StockDeliveryTabsEnum::SHOWCASE             => [
                'title' => __('Settings'),
                'icon'  => 'fal fa-sliders-h',
            ],
            StockDeliveryTabsEnum::ATTACHMENTS          => [
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon',
                'align' => 'right',
            ],
            StockDeliveryTabsEnum::HISTORY              => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
