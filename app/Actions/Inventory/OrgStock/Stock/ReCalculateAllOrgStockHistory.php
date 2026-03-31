<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Mar 2026 23:46:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ReCalculateAllOrgStockHistory
{
    use AsAction;


    public function handle(?Command $command = null): void
    {
        $from = $this->getFirstPurchase();
        $to   = Carbon::yesterday();

        if (!$from || !$to || $from->greaterThan($to)) {
            return;
        }

        $period = CarbonPeriod::create($from, '1 day', $to);

        $numberDays = count($period->toArray());
        $command?->info('Calculating '.$numberDays.' days of history');
        foreach (array_reverse($period->toArray()) as $date) {
            $command?->info('Processing '.$date->format('Y-m-d'));
            CalculateAllOrgStocksDayOrgStockHistory::dispatch($date->format('Y-m-d'));
        }
    }


    public function getFirstPurchase(): ?Carbon
    {
        $rawDate = DB::table('org_stock_movements')->select('date')
            ->where('type', OrgStockMovementTypeEnum::PURCHASE->value)->orderby('date')->first();
        if ($rawDate) {
            return Carbon::parse($rawDate->date)->startOfDay();
        }

        return null;
    }


    public function getCommandSignature(): string
    {
        return 'calculate:all_org_stock_history';
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }

}
