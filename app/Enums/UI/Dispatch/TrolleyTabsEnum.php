<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 14:19:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Dispatch;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum TrolleyTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE                       = 'showcase';

    public function blueprint(): array
    {
        return match ($this) {
            TrolleyTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
