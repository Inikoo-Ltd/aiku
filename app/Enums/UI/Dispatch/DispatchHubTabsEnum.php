<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 */

namespace App\Enums\UI\Dispatch;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum DispatchHubTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DELIVERY_NOTE = 'delivery_note';
    case PICKING_SESSION = 'picking_session';

    public function blueprint(): array
    {
        return match ($this) {
            DispatchHubTabsEnum::DELIVERY_NOTE => [
                'title' => __('Delivery Note'),
                'icon'  => 'fal fa-truck',
            ],
            DispatchHubTabsEnum::PICKING_SESSION => [
                'title' => __('Picking Session'),
                'icon'  => 'fab fa-stack-overflow',
            ],
        };
    }
}
