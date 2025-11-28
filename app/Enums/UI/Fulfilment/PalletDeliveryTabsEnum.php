<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 15:17:32 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithQuantity;
use App\Models\Fulfilment\PalletDelivery;

enum PalletDeliveryTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;

    case PALLETS        = 'pallets';
    case SERVICES       = 'services';
    case PHYSICAL_GOODS = 'physical_goods';


    case ATTACHMENTS = 'attachments';
    case HISTORY = 'history';

    public function blueprint(PalletDelivery $parent): array
    {
        return match ($this) {
            PalletDeliveryTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            PalletDeliveryTabsEnum::ATTACHMENTS => [
                'align' => 'right',
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon'
            ],
            PalletDeliveryTabsEnum::PALLETS => [
                'title'     => __("pallets")." ({$parent->stats->number_pallets})",
                'icon'      => 'fal fa-pallet',
                'indicator' => $parent->pallets()->whereNotNull('location_id')->count() < $parent->pallets()->count()
            ],
            PalletDeliveryTabsEnum::SERVICES => [
                'title' => __("services")." ({$parent->stats->number_services})",
                'icon'  => 'fal fa-concierge-bell',
            ],
            PalletDeliveryTabsEnum::PHYSICAL_GOODS => [
                'title' => __("physical goods")." ({$parent->stats->number_physical_goods})",
                'icon'  => 'fal fa-cube',
            ],
        };
    }
}
