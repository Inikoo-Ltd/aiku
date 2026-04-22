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
        return (float) ($orgStock->unit_cost ?? 0);
    }
}
