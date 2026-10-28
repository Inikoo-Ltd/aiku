<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum SupplierPortalAccessTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case OVERVIEW = 'overview';
    case VISITS = 'visits';

    public function blueprint(): array
    {
        return match ($this) {
            self::OVERVIEW => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-info-circle',
            ],
            self::VISITS => [
                'title' => __('Visits'),
                'icon'  => 'fal fa-eye',
            ],
        };
    }
}
