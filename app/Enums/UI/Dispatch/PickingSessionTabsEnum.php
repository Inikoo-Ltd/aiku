<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:17:42 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Dispatch;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PickingSessionTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ITEMS = 'items';
    // case PICKINGS = 'pickings';


    public function blueprint(): array
    {

        return match ($this) {
            PickingSessionTabsEnum::ITEMS => [
                'title' => __('Items'),
                'icon'  => 'fal fa-bars',
            ],
        };
    }
}
