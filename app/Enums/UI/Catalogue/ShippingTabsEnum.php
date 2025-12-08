<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 09:29:02 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ShippingTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SCHEMAS = 'schemas';
    case CURRENT = 'current';
    case OFFER = 'offer';
    case HISTORY = 'history';

    public function blueprint(): array
    {
        return match ($this) {

            ShippingTabsEnum::CURRENT => [
                'title' => __('Current'),
                'icon' => 'fal fa-shopping-cart',
            ],
            ShippingTabsEnum::OFFER => [
                'title' => __('Offer'),
                'icon' => 'fal fa-chart-pie',
            ],
            ShippingTabsEnum::SCHEMAS => [
                'title' => __('Schemas'),
                'icon' => 'fal fa-tags',
            ],
            ShippingTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
        };
    }
}
