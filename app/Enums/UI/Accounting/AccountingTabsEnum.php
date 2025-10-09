<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:12:14 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Accounting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum AccountingTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case ITEMS                 = 'items';
    case PAYMENTS              = 'payments';
    case PROPERTIES_OPERATIONS = 'properties_operations';

    case CHANGELOG = 'changelog';


    public function blueprint(): array
    {
        return match ($this) {
            AccountingTabsEnum::ITEMS => [
                'title' => __('Items'),
                'icon'  => 'fal fa-bars',
            ],
            AccountingTabsEnum::PAYMENTS => [
                'title' => __('Payments'),
                'icon'  => 'fal fa-dollar-sign',
            ],
            AccountingTabsEnum::PROPERTIES_OPERATIONS => [
                'title' => __('Properties/operations'),
                'icon'  => 'fal fa-database',
            ],
            AccountingTabsEnum::CHANGELOG => [
                'title' => __('Changelog'),
                'icon'  => 'fal fa-road',
                'type'  => 'icon-only',
            ],
        };
    }
}
