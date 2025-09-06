<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Mar 2023 05:16:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Goods\TradeUnit;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class TradeUnitHydrateCostPrice implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(TradeUnit $tradeUnit): string
    {
        return $tradeUnit->id;
    }


    public function handle(TradeUnit $tradeUnit): void
    {
        $tradeUnit->update([
            'cost_price' => $this->getStockValue($tradeUnit),
        ]);
    }


    public function getStockValue(TradeUnit $tradeUnit)
    {
        return $tradeUnit->stocks->first()->unit_value ?? 0;
    }

    public string $commandSignature = 'hydrate:trade_units_cost_price';

    public function asCommand(Command $command): void
    {
        $count = TradeUnit::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        TradeUnit::orderBy('id', 'desc')
            ->chunk(100, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->newLine();
        $command->info("Hydrated $count trade units.");
    }



}
