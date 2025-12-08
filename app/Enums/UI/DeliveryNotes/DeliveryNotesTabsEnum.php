<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 09:29:02 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\DeliveryNotes;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum DeliveryNotesTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DELIVERY_NOTES = 'notes';
    case STATS = 'stats';
    case HISTORY = 'history';

    public function blueprint(): array
    {
        return match ($this) {

            DeliveryNotesTabsEnum::DELIVERY_NOTES => [
                'title' => __('Delivery notes'),
                'icon' => 'fal fa-shopping-cart',
            ],
            DeliveryNotesTabsEnum::STATS => [
                'title' => __('Stats'),
                'icon' => 'fal fa-chart-pie',
            ],

            DeliveryNotesTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ]
        };
    }
}
