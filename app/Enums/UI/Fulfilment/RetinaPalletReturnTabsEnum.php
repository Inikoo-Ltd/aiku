<?php

/*
 * author Arya Permana - Kirin
 * created on 20-03-2025-13h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithQuantity;
use App\Models\Fulfilment\PalletReturn;

enum RetinaPalletReturnTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;

    case GOODS           = 'goods';
    case STORED_ITEMS      = 'stored_items';

    case SERVICES       = 'services';
    case PHYSICAL_GOODS = 'physical_goods';


    case ATTACHMENTS = 'attachments';

    case HISTORY = 'history';

    public function blueprint(PalletReturn $parent): array
    {
        return match ($this) {
            RetinaPalletReturnTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            RetinaPalletReturnTabsEnum::ATTACHMENTS => [
                'align' => 'right',
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon'
            ],
            RetinaPalletReturnTabsEnum::GOODS => [
                'title'     => __("goods")." (" . $parent->stats->number_pallets . ")",
                'icon'      => 'fal fa-pallet',
                'indicator' => $parent->pallets()->whereNotNull('location_id')->count() < $parent->pallets()->count() // todo review this
            ],
            RetinaPalletReturnTabsEnum::STORED_ITEMS => [
               'title'      => __("Stored Items") . " (" . $parent->storedItems()->count() . ")",
                'icon'      => 'fal fa-narwhal',
                'indicator' => false// todo review this
            ],
            RetinaPalletReturnTabsEnum::SERVICES => [
                'title' => __("services")." ({$parent->stats->number_services})",
                'icon'  => 'fal fa-concierge-bell',
            ],
            RetinaPalletReturnTabsEnum::PHYSICAL_GOODS => [
                'title' => __("physical goods")." ({$parent->stats->number_physical_goods})",
                'icon'  => 'fal fa-cube',
            ],
        };
    }
}
