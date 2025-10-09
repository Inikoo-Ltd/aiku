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

    case SHOWCASE            = 'SHOWCASE';

    case ITEMS               = 'items';

    case HISTORY             = 'history';
    case ATTACHMENTS         = 'attachments';
    case DATA                = 'data';





    public function blueprint(): array
    {
        return match ($this) {
            StockDeliveryTabsEnum::DATA     => [
                'title' => __('Data'),
                'icon'  => 'fal fa-database',
                'type'  => 'icon',
                'align' => 'right',
            ],
            StockDeliveryTabsEnum::ITEMS  => [
                'title' => __('Items'),
                'icon'  => 'fal fa-bars',
            ],
            StockDeliveryTabsEnum::SHOWCASE => [
                'title' => __('Supplier delivery'),
                'icon'  => 'fal fa-info-circle',
            ],
            StockDeliveryTabsEnum::HISTORY     => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            StockDeliveryTabsEnum::ATTACHMENTS => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',

            ],
        };
    }
}
