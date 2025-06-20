<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Jun 2025 15:36:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */



/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Goods;

use App\Models\Goods\TradeUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class AddMissingStatsToTradeUnits
{
    use AsAction;


    public function handle(): void
    {
        $tadeUnits = DB::table('trade_units')->get()->pluck('id');

        foreach ($tadeUnits as $tradeUnit) {
            $tradeUnit = TradeUnit::where('id', $tradeUnit)->first();
            if ($tradeUnit) {
                if (!$tradeUnit->stats) {
                    $tradeUnit->stats()->create();
                }
            }

        }
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:add_missing_stats_to_trade_units';
    }

    public function asCommand(Command $command): int
    {
        try {
            $this->handle();
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }

}
