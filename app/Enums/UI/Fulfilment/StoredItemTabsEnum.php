<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:18:05 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum StoredItemTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case PALLETS = 'pallets';
    case MOVEMENTS = 'movements';
    case AUDITS = 'audits';

    case DATA = 'data';
    case HISTORY = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            StoredItemTabsEnum::SHOWCASE => [
                'title' => __('Stored item'),
                'icon' => 'fas fa-info-circle',
            ],
            StoredItemTabsEnum::PALLETS => [
                'title' => __('Pallets'),
                'icon' => 'fal fa-pallet',
            ],
            StoredItemTabsEnum::MOVEMENTS => [
                'title' => __('Movements'),
                'icon' => 'fal fa-exchange',
            ],
            StoredItemTabsEnum::AUDITS => [
                'title' => __('Audits'),
                'icon' => 'fal fa-ballot-check',
            ],
            StoredItemTabsEnum::DATA => [
                'align' => 'right',
                'type' => 'icon',
                'title' => __('Data'),
                'icon' => 'fal fa-database',
            ],
            StoredItemTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
        };
    }
}
