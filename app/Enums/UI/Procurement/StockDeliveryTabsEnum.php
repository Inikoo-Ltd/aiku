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

    case SHOWCASE    = 'showcase';
    case ITEMS       = 'items';
    case ATTACHMENTS = 'attachments';
    case HISTORY     = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            StockDeliveryTabsEnum::SHOWCASE    => [
                'title' => __('Data'),
                'icon'  => 'fal fa-database',
            ],
            StockDeliveryTabsEnum::ITEMS       => [
                'title' => __('Items'),
                'icon'  => 'fal fa-bars',
            ],
            StockDeliveryTabsEnum::ATTACHMENTS => [
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon',
                'align' => 'right',
            ],
            StockDeliveryTabsEnum::HISTORY     => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
