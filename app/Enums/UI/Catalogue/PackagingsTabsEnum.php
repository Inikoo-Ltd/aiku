<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 10:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PackagingsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case PACKAGINGS = 'packagings';
    case LEAFLETS = 'leaflets';

    public function blueprint(): array
    {
        return match ($this) {
            PackagingsTabsEnum::PACKAGINGS => [
                'title' => __('Packagings'),
                'icon'  => 'fal fa-box-open',
            ],
            PackagingsTabsEnum::LEAFLETS => [
                'title' => __('Leaflets'),
                'icon'  => 'fal fa-file-alt',
            ],
        };
    }
}
