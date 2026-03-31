<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Mar 2026 01:15:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Actions\Maintenance\Inventory\OrgStockMovement\RepairOrgStockMovements;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateOrgStockHistory implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'stock-history';

    public function getJobUniqueId(OrgStock $orgStock): string
    {
        return $orgStock->id;
    }


    public function handle(OrgStock $orgStock, ?Command $command = null): void
    {
        return;
        $from = $this->getFirstAssociateDate($orgStock);
        if (!$from) {
            $command?->info('Skipping '.$orgStock->slug.' ('.$orgStock->id.') - no associate date');

            return;
        }

        $minimumDate = Carbon::parse('2016-10-01');
        if ($from->lt($minimumDate)) {
            $from = $minimumDate;
        }
        $maxDate = Carbon::parse('2020-01-02');

        $isCurrent = DB::table('location_org_stocks')->where('org_stock_id', $orgStock->id)->exists();
        if ($isCurrent) {
            $to = Carbon::now();
        } else {
            $to = $this->getLastDisassociateDate($orgStock);
        }

        if ($to->gt($maxDate)) {
            $to = $maxDate;
        }

        if ($to->lt($from)) {
            $command?->info('Skipping '.$orgStock->slug.' ('.$orgStock->id.') - to date is before from date');

            return;
        }


        $days = (int)$from->diffInDays($to) + 1;
        $command?->info('Calculating '.$orgStock->slug.' ('.$orgStock->id.') from '.$from->format('Y-m-d').' to '.$to->format('Y-m-d').' ('.$days.' days)');
        foreach (Carbon::parse($from)->daysUntil($to) as $date) {
            StoreOrgStockHistoricLocationsStock::run($orgStock, $date, $command);
        }
    }

    public function getLastDisassociateDate(OrgStock $orgStock): ?Carbon
    {
        $rawDate = DB::table('org_stock_movements')->select('date')->where('org_stock_id', $orgStock->id)
            ->where('type', OrgStockMovementTypeEnum::DISASSOCIATE->value)->orderby('date', 'desc')->first();
        if ($rawDate) {
            return Carbon::parse($rawDate->date);
        }

        return null;
    }

    public function getFirstAssociateDate(OrgStock $orgStock): ?Carbon
    {
        $rawDate = DB::table('org_stock_movements')->select('date')->where('org_stock_id', $orgStock->id)
            ->where('type', OrgStockMovementTypeEnum::ASSOCIATE->value)->orderby('date')->first();
        if ($rawDate) {
            return Carbon::parse($rawDate->date);
        }

        return null;
    }


    public function getCommandSignature(): string
    {
        return 'calculate:org_stock_history {orgStock?} {--f|fix_movements}';
    }

    public function asCommand(Command $command): int
    {
        $fixMovements = $command->option('fix_movements');
        if ($fixMovements) {
            $command->info('Fixing movements');
        }
        if ($command->argument('orgStock')) {
            $orgStock = OrgStock::where('slug', $command->argument('orgStock'))->firstOrFail();
            if ($fixMovements) {
                RepairOrgStockMovements::run($orgStock, $command);
            }
            $this->handle($orgStock, $command);

            return 0;
        }


        /** @var OrgStock $orgStock */
        foreach (OrgStock::orderBy('id', 'desc')->get() as $orgStock) {
            if ($fixMovements) {
                RepairOrgStockMovements::run($orgStock, $command);
            }
            $command->info('Processing '.$orgStock->slug.' ('.$orgStock->id.')');
            $this->handle($orgStock, $command);
            //  CalculateOrgStockHistory::dispatch($orgStock);
        }

        return 0;
    }

}
