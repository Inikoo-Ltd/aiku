<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 May 2025 11:05:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Goods;

use App\Actions\Goods\TradeUnitFamily\StoreTradeUnitFamily;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnitFamily;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Helper\ProgressBar;

class SyncTradeUnitFamiliesAndStockFamilies
{
    use WithActionUpdate;


    public function handle(Stock $stock): void
    {
        $tradeUnits = $stock->tradeUnits;
        $stockFamily = $stock->stockFamily;
        $existingTradeUnitFamily = TradeUnitFamily::where('code', $stockFamily->code)->first();

        foreach ($tradeUnits as $tradeUnit) {
            if ($existingTradeUnitFamily && !$tradeUnit->tradeUnitFamily) {
                $this->update($tradeUnit, [
                    'trade_unit_family_id' => $existingTradeUnitFamily->id
                ]);
            } else {
                $newTradeUnitFamily = StoreTradeUnitFamily::make()->action($tradeUnit->group, [
                    'code' => $stockFamily->code,
                    'name' => $stockFamily->name,
                    'description' => $stockFamily->description,
                ]);

                $this->update($tradeUnit, [
                    'trade_unit_family_id' => $newTradeUnitFamily->id
                ]);
            }
        }
    }


    public string $commandSignature = 'maintenance:sync_trade_unit_families_and_stock_families';

    public function asCommand(Command $command): void
    {
        $count = Stock::whereNotNull('stock_family_id')->count();

        ProgressBar::setFormatDefinition(
            'aiku_eta',
            ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
        );
        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('aiku_eta');
        $bar->start();

        Stock::orderBy('id')->whereNotNull('stock_family_id')
            ->chunk(100, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });
    }

}
