<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 03:02:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\StoredItem;

use App\Enums\EnumHelperTrait;
use App\Models\Fulfilment\PalletReturn;

enum StoredItemInReturnOptionEnum: string
{
    use EnumHelperTrait;

    case ALL_STORED_PALLETS = 'all_stored_pallets';
    case SELECTED = 'selected';

    public static function labels(): array
    {
        return [
            'selected' => __('Selected'),
            'all_stored_pallets' => __('All stored pallets'),

        ];
    }

    public static function count(PalletReturn $palletReturn): array
    {
        return [
            'all_stored_pallets' => $palletReturn->fulfilmentCustomer->number_pallets_status_storing,
            'selected' => $palletReturn->stats->number_pallets,
        ];
    }
}
