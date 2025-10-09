<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Aug 2024 17:14:04 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrgPartnerTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case SHOWCASE = 'showcase';


    public function blueprint(): array
    {
        return match ($this) {
            OrgPartnerTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
