<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 22 Aug 2024 10:25:01 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum StoredItemsInWarehouseTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case STORED_ITEMS = 'stored_items';

    case PALLET_STORED_ITEMS = 'pallet_stored_items';

    case STORED_ITEM_AUDITS = 'stored_item_audits';

    public function blueprint(): array
    {
        return match ($this) {
            StoredItemsInWarehouseTabsEnum::STORED_ITEMS => [
                'title' => __("Customer'S SKUs"),
                'icon' => 'fal fa-narwhal',
            ],

            StoredItemsInWarehouseTabsEnum::PALLET_STORED_ITEMS => [

                'title' => __("Pallet/Customer's SKUs"),
                'icon' => 'fal fa-pallet',
            ],
            StoredItemsInWarehouseTabsEnum::STORED_ITEM_AUDITS => [

                'title' => __("Customer's SKUs Audits"),
                'icon' => 'fal fa-ballot-check',
            ],

        };
    }
}
