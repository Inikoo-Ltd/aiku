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
    case UK_RETURN    = 'uk_return';
    case EU_RETURN    = 'eu_return';
    case SHIPMENT     = 'shipment';
    case EXPORTS      = 'exports';

    public function blueprint(): array
    {
        return match ($this) {
            self::COMPLETENESS => [
                'title' => __('Completeness'),
                'icon'  => 'fal fa-tasks',
            ],
            self::UK_RETURN => [
                'title' => __('UK return'),
                'icon'  => 'fal fa-file-certificate',
            ],
            self::EU_RETURN => [
                'title' => __('Scheme return'),
                'icon'  => 'fal fa-file-certificate',
            ],
            self::SHIPMENT => [
                'title' => __('Shipment packaging'),
                'icon'  => 'fal fa-box-open',
            ],
            self::EXPORTS => [
                'title' => __('Spreadsheet exports'),
                'icon'  => 'fal fa-file-csv',
            ],
        };
    }
}
