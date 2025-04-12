<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Apr 2025 15:51:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use App\Models\Accounting\InvoiceCategory;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Shop;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

trait WithIntervalUniqueJob
{
    public function getUniqueJobWithInterval(Group|Organisation|Shop|InvoiceCategory|Asset|StockFamily|Stock $stock, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        $uniqueId = $stock->id;
        if ($intervals !== null) {
            $intervalValues = [];
            foreach ($intervals as $interval) {
                if (is_object($interval) && property_exists($interval, 'value')) {
                    $intervalValues[] = $interval->value;
                } else {
                    $intervalValues[] = $interval;
                }
            }

            $uniqueId .= '-'.implode('-', $intervalValues);
        }
        if ($doPreviousPeriods !== null) {
            $uniqueId .= '-'.implode('-', $doPreviousPeriods);
        }


        return $uniqueId;
    }
}
