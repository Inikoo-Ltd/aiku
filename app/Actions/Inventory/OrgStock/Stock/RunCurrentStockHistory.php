<?php

/*
 * author Louis Perez
 * created on 31-03-2026-15h-43m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Inventory\OrgStock\Stock;


use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RunCurrentStockHistory
{
    use AsAction;


    public function handle(): void
    {
        DB::table('location_org_stocks')
            ->select('org_stock_id')
            ->distinct()
            ->orderBy('org_stock_id')
            ->chunkById(100, function ($locationOrgStocks) {
                foreach ($locationOrgStocks as $locationOrgStock) {
                    CalculateOrgStockCurrentStockHistories::run($locationOrgStock->org_stock_id);
                }
            }, 'org_stock_id');
    }

    public function getCommandSignature(): string
    {
        return 'run:current_stock_history';
    }

    public function asCommand(): int
    {
        $this->handle();
        return 0;
    }

}
