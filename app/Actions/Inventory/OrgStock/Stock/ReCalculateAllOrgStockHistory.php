<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Mar 2026 23:46:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\SysAdmin\Organisation;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ReCalculateAllOrgStockHistory
{
    use AsAction;


    public function handle(Organisation $organisation, ?Command $command = null): void
    {
        $from = $this->getFirstPurchase($organisation);
        $to   = Carbon::yesterday();

        if (!$from || !$to || $from->greaterThan($to)) {
            return;
        }

        $period = CarbonPeriod::create($from, '1 day', $to);

        $numberDays = count($period->toArray());
        $command?->info('Calculating '.$numberDays.' days of history');
        foreach (array_reverse($period->toArray()) as $date) {
            CalculateAllOrgStocksDayOrgStockHistory::dispatch($organisation->id, $date->format('Y-m-d'));
            sleep(1);
        }
    }


    public function getFirstPurchase(Organisation $organisation): ?Carbon
    {
        $rawDate = DB::table('org_stock_movements')->select('date')
            ->where('organisation_id', $organisation->id)
            ->where('type', OrgStockMovementTypeEnum::PURCHASE->value)->orderby('date')->first();
        if ($rawDate) {
            return Carbon::parse($rawDate->date)->startOfDay();
        }

        return null;
    }


    public function getCommandSignature(): string
    {
        return 'calculate:all_org_stock_history {organisation}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $this->handle($organisation, $command);

        return 0;
    }

}
