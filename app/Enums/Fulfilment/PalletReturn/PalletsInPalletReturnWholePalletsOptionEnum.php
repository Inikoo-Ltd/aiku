<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Feb 2025 14:43:50 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\PalletReturn;

use App\Enums\EnumHelperTrait;
use App\Models\Fulfilment\PalletReturn;

enum PalletsInPalletReturnWholePalletsOptionEnum: string
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
