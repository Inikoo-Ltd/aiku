<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock;

use App\Actions\Goods\Stock\Hydrators\StockHydrateStateFromOrgStocks;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Models\Goods\Stock;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One-off repair for INI-070. A stock's state is derived from its org stocks and nothing else:
 * active while any organisation still has it live, discontinuing once none does but some are still
 * selling it off, discontinued when all are. Aurora set it from one organisation's part status
 * (and filed 'Discontinuing' as discontinued), and from 11 Dec 2025 org stock state changes never
 * reached it, so this recalculates every stock once. Stocks without org stocks keep their state.
 * Every write is audited on the stock with its old state.
 */
class RepairStocksStateFromOrgStocks
{
    use AsAction;

    public string $commandSignature = 'stocks:repair_state_from_org_stocks
        {--apply : Write the states, without this the command only reports what it would do}';

    /**
     * @return array{0: StockStateEnum, 1: StockStateEnum}|null
     */
    public function handle(Stock $stock, bool $apply): ?array
    {
        $oldState = $stock->state;
        $newState = StockHydrateStateFromOrgStocks::make()->getStockStateFromOrgStocks($stock);

        if ($oldState === $newState) {
            return null;
        }

        if ($apply) {
            StockHydrateStateFromOrgStocks::run($stock->id);
        }

        return [$oldState, $newState];
    }

    public function asCommand(Command $command): int
    {
        $apply   = (bool)$command->option('apply');
        $changes = [];

        Stock::with('orgStocks')->chunkById(500, function ($stocks) use ($apply, &$changes, $command) {
            foreach ($stocks as $stock) {
                if ($change = $this->handle($stock, $apply)) {
                    [$oldState, $newState] = $change;
                    $command->line("$stock->code: $oldState->value -> $newState->value");
                    $changes[] = "$oldState->value -> $newState->value";
                }
            }
        });

        foreach (array_count_values($changes) as $change => $count) {
            $command->info("$change: $count");
        }
        $command->info(($apply ? 'Repaired ' : 'Would repair ').count($changes).' stocks');

        return 0;
    }
}
