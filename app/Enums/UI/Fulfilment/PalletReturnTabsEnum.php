<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 15:17:32 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithQuantity;
use App\Models\Fulfilment\PalletReturn;

enum PalletReturnTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;

    case PALLETS = 'pallets';
    case STORED_ITEMS = 'stored_items';
    case SERVICES = 'services';
    case PHYSICAL_GOODS = 'physical_goods';

    case ATTACHMENTS = 'attachments';

    case HISTORY = 'history';

    public function blueprint(PalletReturn $parent): array
    {
        return match ($this) {
            PalletReturnTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
            PalletReturnTabsEnum::ATTACHMENTS => [
                'align' => 'right',
                'title' => __('Attachments'),
                'icon' => 'fal fa-paperclip',
                'type' => 'icon',
            ],
            PalletReturnTabsEnum::PALLETS => [
                'title' => __('Pallets').' ('.$parent->stats->number_pallets.')',
                'icon' => 'fal fa-pallet',
                'indicator' => $parent->pallets()->whereNotNull('location_id')->count() < $parent->pallets()->count(), // todo review this
            ],
            PalletReturnTabsEnum::STORED_ITEMS => [
                'title' => __('Stored Items').' ('.$parent->storedItems()->count().')',
                'icon' => 'fal fa-narwhal',
                'indicator' => false, // todo review this
            ],
            PalletReturnTabsEnum::SERVICES => [
                'title' => __('Services')." ({$parent->stats->number_services})",
                'icon' => 'fal fa-concierge-bell',
            ],
            PalletReturnTabsEnum::PHYSICAL_GOODS => [
                'title' => __('Physical goods')." ({$parent->stats->number_physical_goods})",
                'icon' => 'fal fa-cube',
            ],
        };
    }
}
