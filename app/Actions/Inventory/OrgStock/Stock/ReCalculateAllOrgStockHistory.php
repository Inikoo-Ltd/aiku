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


    public function handle(Organisation $organisation, ?Command $command = null, bool $async = false, string $interval = 'd'): void
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
            if ($interval == 'w' && !$date->isFriday()) {
                continue;
            }
            if ($interval == 'm' && !$date->isLastOfMonth()) {
                continue;
            }
            if ($interval == 'y' && !$date->isEndOfYear()) {
                continue;
            }

            if ($async) {
                CalculateAllOrgStocksDayOrgStockHistory::dispatch($organisation->id, $date->format('Y-m-d'));
                sleep(1);
            } else {
                $command?->info('Calculating '.$date->format('Y-m-d'));
                CalculateAllOrgStocksDayOrgStockHistory::run($organisation->id, $date->format('Y-m-d'));
            }
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
        return 'calculate:all_org_stock_history {organisation} {--a|async} {--i|interval=d : Interval (d=day, w=week, m=month, y=year)}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $async        = $command->option('async');
        $interval     = $command->option('interval');

        if (!in_array($interval, ['d', 'w', 'm', 'y'])) {
            $command->error('Invalid interval value. Accepted values: d, w, m, y');

            return 1;
        }

        $this->handle($organisation, $command, $async, $interval);

        return 0;
    }

}
