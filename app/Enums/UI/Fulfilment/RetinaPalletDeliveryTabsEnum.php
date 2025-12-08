<?php

/*
 * author Arya Permana - Kirin
 * created on 20-03-2025-13h-05m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithQuantity;
use App\Models\Fulfilment\PalletDelivery;

enum RetinaPalletDeliveryTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;

    case GOODS = 'goods';
    case SERVICES = 'services';
    case PHYSICAL_GOODS = 'physical_goods';

    case ATTACHMENTS = 'attachments';
    case HISTORY = 'history';

    public function blueprint(PalletDelivery $parent): array
    {
        return match ($this) {
            RetinaPalletDeliveryTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
            RetinaPalletDeliveryTabsEnum::ATTACHMENTS => [
                'align' => 'right',
                'title' => __('Attachments'),
                'icon' => 'fal fa-paperclip',
                'type' => 'icon',
            ],
            RetinaPalletDeliveryTabsEnum::GOODS => [
                'title' => __('goods')." ({$parent->stats->number_pallets})",
                'icon' => 'fal fa-pallet',
                'indicator' => $parent->pallets()->whereNotNull('location_id')->count() < $parent->pallets()->count(),
            ],
            RetinaPalletDeliveryTabsEnum::SERVICES => [
                'title' => __('services')." ({$parent->stats->number_services})",
                'icon' => 'fal fa-concierge-bell',
            ],
            RetinaPalletDeliveryTabsEnum::PHYSICAL_GOODS => [
                'title' => __('physical goods')." ({$parent->stats->number_physical_goods})",
                'icon' => 'fal fa-cube',
            ],
        };
    }
}
