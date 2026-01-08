<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Aug 2024 17:14:04 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrgStockTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';

    case TRADE_UNITS = 'trade_units';
    case HISTORY = 'history';
    case IMAGES = 'images';
    case ATTACHMENTS = 'attachments';
    case FEEDBACKS = 'feedbacks';


    public function blueprint(): array
    {
        return match ($this) {
            OrgStockTabsEnum::HISTORY => [
                'align' => 'right',
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
            ],
            OrgStockTabsEnum::FEEDBACKS => [
                'title' => __('Issues'),
                'icon'  => 'fal fa-poop',
            ],
            OrgStockTabsEnum::TRADE_UNITS => [
                'title' => __('Trade units'),
                'icon'  => 'fal fa-atom',
            ],

            OrgStockTabsEnum::ATTACHMENTS => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',

            ],
            OrgStockTabsEnum::IMAGES => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Images'),
                'icon'  => 'fal fa-camera-retro',
            ],

            OrgStockTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
