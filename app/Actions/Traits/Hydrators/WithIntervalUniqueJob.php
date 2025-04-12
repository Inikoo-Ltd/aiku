<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Apr 2025 15:51:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;

trait WithIntervalUniqueJob
{
    public function getUniqueJobWithInterval(StockFamily|Stock $stock, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        $uniqueId = $stock->id;
        if ($intervals !== null) {
            $uniqueId .= '-'.implode('-', $intervals);
        }
        if ($doPreviousPeriods !== null) {
            $uniqueId .= '-'.implode('-', $doPreviousPeriods);
        }


        return $uniqueId;
    }
}
