<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:49:54 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum FulfilmentAssetTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE   = 'showcase';
    case HISTORY    = 'history';



    public function blueprint(): array
    {
        return match ($this) {
            FulfilmentAssetTabsEnum::SHOWCASE => [
                'title' => __('Details'),
                'icon'  => 'fas fa-info-circle',
            ],
            FulfilmentAssetTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
