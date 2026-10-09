<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Reports;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PackagingReportTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case COMPLETENESS = 'completeness';
    case EXPORTS      = 'exports';

    public function blueprint(): array
    {
        return match ($this) {
            self::COMPLETENESS => [
                'title' => __('Completeness'),
                'icon'  => 'fal fa-tasks',
            ],
            self::EXPORTS => [
                'title' => __('Spreadsheet exports'),
                'icon'  => 'fal fa-file-csv',
            ],
        };
    }
}
