<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 08 Jun 2025 13:09:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Ordering;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum BasketTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case TRANSACTIONS = 'transactions';


    public function blueprint(): array
    {
        return match ($this) {
            BasketTabsEnum::TRANSACTIONS => [
                'title' => __('items'),
                'icon'  => 'fal fa-bars',
            ],
        };
    }
}
