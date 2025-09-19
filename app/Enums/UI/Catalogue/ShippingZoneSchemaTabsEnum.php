<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 09:29:02 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ShippingZoneSchemaTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    // case SHOWCASE      = 'showcase';
    case ZONES         = 'zones';
    case HISTORY       = 'history';


    public function blueprint(): array
    {
        return match ($this) {

            // ShippingZoneSchemaTabsEnum::SHOWCASE => [
            //     'title' => __('details'),
            //     'icon'  => 'fal fa-shopping-cart',
            // ],
            ShippingZoneSchemaTabsEnum::ZONES => [
                'title' => __('zones'),
                'icon'  => 'fal fa-map',
            ],
            ShippingZoneSchemaTabsEnum::HISTORY => [
                'title' => __('history'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right'
            ],
        };
    }
}
