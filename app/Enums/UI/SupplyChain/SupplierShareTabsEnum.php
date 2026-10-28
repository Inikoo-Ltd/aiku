<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum SupplierShareTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SELF_LOG_IN = 'self_log_in';
    case DIRECT_LINKS = 'direct_links';

    public function blueprint(): array
    {
        return match ($this) {
            self::SELF_LOG_IN => [
                'title' => __('Self log-in'),
                'icon'  => 'fal fa-envelope',
            ],
            self::DIRECT_LINKS => [
                'title' => __('Direct links'),
                'icon'  => 'fal fa-link',
            ],
        };
    }
}
