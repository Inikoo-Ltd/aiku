<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Inventory;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrgStockLabelsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case LABELS = 'labels';
    case COMPLIANCE = 'compliance';

    public function blueprint(): array
    {
        return match ($this) {
            OrgStockLabelsTabsEnum::LABELS => [
                'title' => __('Labels'),
                'icon'  => 'fal fa-tags',
            ],
            OrgStockLabelsTabsEnum::COMPLIANCE => [
                'title' => __('Compliance'),
                'icon'  => 'fal fa-clipboard-check',
            ],
        };
    }
}
