<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jul 2024 00:55:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum FulfilmentCustomerStoredItemsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case STORED_ITEMS = 'stored_items';

    case PALLET_STORED_ITEMS = 'pallet_stored_items';

    public function blueprint(): array
    {
        return match ($this) {
            StoredItemsInWarehouseTabsEnum::STORED_ITEMS => [
                'title' => __("Customer'S SKUs"),
                'icon' => 'fal fa-narwhal',
            ],

            StoredItemsInWarehouseTabsEnum::PALLET_STORED_ITEMS => [
                'align' => 'right',
                'type' => 'icon',
                'title' => __('Pallet Stored Items'),
                'icon' => 'fal fa-pallet',
            ],

        };
    }
}
