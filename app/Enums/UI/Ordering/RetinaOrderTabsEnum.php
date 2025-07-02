<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Jul 2025 23:53:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Ordering;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RetinaOrderTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case TRANSACTIONS                       = 'transactions';




    public function blueprint(): array
    {
        return match ($this) {

            RetinaOrderTabsEnum::TRANSACTIONS => [
                'title' => __('transactions'),
                'icon'  => 'fal fa-bars',
            ],


        };
    }
}
