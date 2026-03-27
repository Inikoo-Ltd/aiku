<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Models\Inventory\OrgStock;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GetOrgStockValue
{
    use AsAction;

    public function handle(OrgStock $orgStock, ?Carbon $date = null): float
    {
        // TODO: Use $date to retrieve historical value based on old transaction data,
        // instead of the current OrgStock average value which may have been updated since.

        $average = ((float) ($orgStock->unit_commercial_value ?? 0) + (float) ($orgStock->unit_cost ?? 0)) / 2;

        if ($average <= 0 || !is_finite($average)) {
            return 0.1;
        }

        return $average;
    }
}
