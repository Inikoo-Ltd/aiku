<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:17:42 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Dispatch;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithIndicator;
use App\Models\Dispatching\DeliveryNote;

enum DeliveryNoteTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithIndicator;

    case ITEMS = 'items';
    // case PICKINGS = 'pickings';


    public function blueprint(DeliveryNote $parent): array
    {

        return match ($this) {
            DeliveryNoteTabsEnum::ITEMS => [
                'title' => __('Items'),
                'icon'  => 'fal fa-bars',
            ],
            // DeliveryNoteTabsEnum::PICKINGS => [
            //     'title'     => __('pickings '),
            //     'icon'      => 'fal fa-box-full',
            //     'type'      => 'icon',
            //     'align'     => 'right',
            //     'indicator' => $indicator
            // ],
        };
    }
}
