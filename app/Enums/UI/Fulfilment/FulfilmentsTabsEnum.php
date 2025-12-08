<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 15:17:08 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum FulfilmentsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case FULFILMENT_SHOPS = 'fulfilments';

    public function blueprint(): array
    {
        return match ($this) {

            FulfilmentsTabsEnum::FULFILMENT_SHOPS => [
                'title' => __('Fulfilment shops'),
                'icon' => 'fal fa-bars',
            ],

        };
    }
}
