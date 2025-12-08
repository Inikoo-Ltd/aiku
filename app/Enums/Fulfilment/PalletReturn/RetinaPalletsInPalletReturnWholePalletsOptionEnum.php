<?php

/*
 * author Arya Permana - Kirin
 * created on 20-03-2025-13h-10m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Fulfilment\PalletReturn;

use App\Enums\EnumHelperTrait;
use App\Models\Fulfilment\PalletReturn;

enum RetinaPalletsInPalletReturnWholePalletsOptionEnum: string
{
    use EnumHelperTrait;

    case ALL_STORED_GOODS = 'all_stored_goods';
    case SELECTED = 'selected';

    public static function labels(): array
    {
        return [
            'selected' => __('Selected'),
            'all_stored_goods' => __('All stored goods'),

        ];
    }

    public static function count(PalletReturn $palletReturn): array
    {
        return [
            'all_stored_goods' => $palletReturn->fulfilmentCustomer->number_pallets_status_storing,
            'selected' => $palletReturn->stats->number_pallets,
        ];
    }
}
