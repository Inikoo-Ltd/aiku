<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Masters;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum CompetitorTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DOSSIER  = 'dossier';
    case MATCHES  = 'matches';
    case PRODUCTS = 'products';
    case POLICIES = 'policies';

    public function blueprint(): array
    {
        return match ($this) {
            CompetitorTabsEnum::DOSSIER => [
                'title' => __('Dossier'),
                'icon'  => 'fal fa-file-alt',
            ],
            CompetitorTabsEnum::MATCHES => [
                'title' => __('Matches'),
                'icon'  => 'fal fa-balance-scale',
            ],
            CompetitorTabsEnum::PRODUCTS => [
                'title' => __('Products read'),
                'icon'  => 'fal fa-cube',
            ],
            CompetitorTabsEnum::POLICIES => [
                'title' => __('Policies'),
                'icon'  => 'fal fa-file-contract',
            ],
        };
    }
}
