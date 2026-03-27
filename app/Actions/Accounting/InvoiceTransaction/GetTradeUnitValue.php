<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTradeUnitValue
{
    use AsAction;

    public function handle(TradeUnit $tradeUnit, Organisation $organisation, ?Carbon $date = null): float
    {
        // TODO: Use $date to retrieve historical value based on old transaction data,
        // instead of the current OrgStock average value which may have been updated since.

        $orgStocks = $tradeUnit->orgStocks()->where('organisation_id', $organisation->id)->get();

        if ($orgStocks->isEmpty()) {
            return 0.1;
        }

        $total = 0.0;
        foreach ($orgStocks as $orgStock) {
            $total += $orgStock->pivot->quantity * GetOrgStockValue::run($orgStock, $date);
        }

        return $total;
    }
}
