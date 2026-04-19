<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Mar 2026 18:50:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateDayOrgStockHistory implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'stock-history';

    public function getJobUniqueId(?int $orgStockId, Carbon $date): string
    {
        return $orgStockId.'-'.Carbon::now()->format('Y-m-d');
    }


    public function handle(?int $orgStockId, Carbon $date, ?Command $command = null): void
    {
        if (!$orgStockId) {
            return;
        }
        $orgStock = OrgStock::find($orgStockId);
        if (!$orgStock) {
            return;
        }

        $date->setTime(12, 0);


        $from = $this->getFirstAssociateDate($orgStock);
        if (!$from) {
            $command?->warn('Skipping '.$orgStock->slug.' ('.$orgStock->id.') - no associate date');

            return;
        }


        $isCurrent = DB::connection('aiku_no_sticky')->table('location_org_stocks')->where('org_stock_id', $orgStock->id)->exists();
        if ($isCurrent) {
            $to = Carbon::now()->endOfDay();
        } else {
            $to = $this->getLastDisassociateDate($orgStock)?->endOfDay();
        }


        if ($to->lt($from)) {
            $command?->warn('Skipping '.$orgStock->slug.' ('.$orgStock->id.') - to date is before from date');

            return;
        }

        if ($date->lt($from) || $date->gt($to)) {
            $command?->warn('Skipping '.$orgStock->slug.' ('.$orgStock->id.') - date '.$date->format('Y-m-d').' is not between '.$from->format('Y-m-d').' and '.$to->format('Y-m-d'));

            return;
        }
        $command?->info('Calculating '.$orgStock->slug.' ('.$orgStock->id.') '.$date->format('Y-m-d'));
        CalculateOrgStockHistoricStockHistories::run($orgStock, $date, $command);
    }

    public function getLastDisassociateDate(OrgStock $orgStock): ?Carbon
    {
        $rawDate = DB::connection('aiku_no_sticky')->table('org_stock_movements')->select('date')->where('org_stock_id', $orgStock->id)
            ->where('type', OrgStockMovementTypeEnum::DISASSOCIATE->value)->orderby('date', 'desc')->first();
        if ($rawDate) {
            return Carbon::parse($rawDate->date);
        }

        return null;
    }

    public function getFirstAssociateDate(OrgStock $orgStock): ?Carbon
    {
        $rawDate = DB::connection('aiku_no_sticky')->table('org_stock_movements')->select('date')->where('org_stock_id', $orgStock->id)
            ->where('type', OrgStockMovementTypeEnum::ASSOCIATE->value)->orderby('date')->first();
        if ($rawDate) {
            return Carbon::parse($rawDate->date)->startOfDay();
        }

        return null;
    }


    public function getCommandSignature(): string
    {
        return 'calculate:org_stock_history_date {date}';
    }

    public function asCommand(Command $command): int
    {
        $date = Carbon::parse($command->argument('date'));

        $orgStocks   = OrgStock::orderBy('id')->get();
        $progressBar = $command->getOutput()->createProgressBar($orgStocks->count());
        $progressBar->setFormat('debug');
        $progressBar->start();

        /** @var OrgStock $orgStock */
        foreach ($orgStocks as $orgStock) {
            $this->handle($orgStock->id, $date);
            $progressBar->advance();
        }

        $progressBar->finish();
        $command->newLine();

        return 0;
    }

}
