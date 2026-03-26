<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GetInvoiceTransactionItemValue
{
    use AsAction;

    public function getOrgStockValue(OrgStock $orgStock, Carbon $date): float
    {
        // TODO: Use $date to retrieve historical value based on old transaction data,
        // instead of the current OrgStock average value which may have been updated since.

        $average = ((float) ($orgStock->unit_commercial_value ?? 0) + (float) ($orgStock->unit_cost ?? 0)) / 2;

        if ($average == 0 || !is_finite($average)) {
            return 0.1;
        }

        if ($average < 0) {
            return 0.0;
        }

        return $average;
    }

    public function getTradeUnitValue(TradeUnit $tradeUnit, Organisation $organisation, Carbon $date): float
    {
        // TODO: Use $date to retrieve historical value based on old transaction data,
        // instead of the current OrgStock average value which may have been updated since.

        $orgStocks = $tradeUnit->orgStocks()->where('organisation_id', $organisation->id)->get();

        if ($orgStocks->isEmpty()) {
            return 0.1;
        }

        $total = 0.0;
        foreach ($orgStocks as $orgStock) {
            $total += $this->getOrgStockValue($orgStock, $date);
        }

        return $total / $orgStocks->count();
    }
}
