<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sept 2025 12:48:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */





/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Goods;

use App\Models\Goods\TradeUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class RepairTradeUnitsMainImageSubScope
{
    use AsAction;


    public function handle(TradeUnit $tradeUnit): void
    {

        DB::table('model_has_media')
            ->where('media_id', $tradeUnit->image_id)
            ->where('model_type', 'TradeUnit')
            ->where('model_id', $tradeUnit->id)->update(['sub_scope' => 'main']);


    }

    public function getCommandSignature(): string
    {
        return 'trade_units:repair_main_image_sub_scope';
    }

    public function asCommand(Command $command): int
    {
        $total = DB::table('trade_units')->count();
        $command->info("Repairing main image sub_scope for $total trade units...");
        $start = microtime(true);
        $processed = 0;

        $bar = new ProgressBar($command->getOutput(), $total);
        $bar->setFormat('verbose');
        $bar->start();

        DB::table('trade_units')
            ->select('id')
            ->orderBy('id')
            ->chunkById(1000, function ($tradeUnitRows) use (&$processed, $bar) {
                foreach ($tradeUnitRows as $row) {
                    $tradeUnit = TradeUnit::find($row->id);
                    if ($tradeUnit) {
                        $this->handle($tradeUnit);
                    }
                    $processed++;
                    $bar->advance();
                }
            }, 'id');

        $bar->finish();
        $command->newLine(2);
        $duration = microtime(true) - $start;
        $command->info("Done. Processed $processed/$total trade units in ".gmdate('H:i:s', (int) $duration).".");

        return 0;
    }

}
