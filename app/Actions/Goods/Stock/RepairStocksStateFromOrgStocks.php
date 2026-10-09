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
use App\Models\Helpers\Audit;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One-off repair for INI-070: from 11 Dec 2025 UpdateOrgStock handed the org stock id to the stock
 * state hydrator, so the stock behind an org stock whose state changed was never recalculated.
 * Only stocks with such an org stock state change are touched; a blanket hydrate:stocks would also
 * flip the thousands of stocks Aurora set discontinued while some organisation still sells them.
 *
 * Only stocks still active are repaired. A stock already discontinued is left alone whatever its
 * org stocks say: Aurora files its own 'Discontinuing' parts as discontinued, and whether a stock
 * stays discontinued while one organisation still sells it is not decided yet. Every write is
 * audited on the stock with its old state.
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

        if ($oldState !== StockStateEnum::ACTIVE || $newState === StockStateEnum::ACTIVE) {
            return null;
        }

        if ($apply) {
            StockHydrateStateFromOrgStocks::run($stock->id);
        }

        return [$oldState, $newState];
    }

    public static function affectedStocks(): Builder
    {
        $changedOrgStockIds = Audit::query()
            ->where('auditable_type', 'OrgStock')
            ->where('event', 'updated')
            ->where('created_at', '>=', '2025-12-11')
            ->whereRaw("jsonb_exists(new_values::jsonb, 'state')")
            ->select('auditable_id');

        return Stock::where('state', StockStateEnum::ACTIVE)->whereHas('orgStocks', fn ($query) => $query->whereIn('org_stocks.id', $changedOrgStockIds));
    }

    public function asCommand(Command $command): int
    {
        $apply   = (bool)$command->option('apply');
        $changes = [];

        static::affectedStocks()->with('orgStocks')->chunkById(500, function ($stocks) use ($apply, &$changes, $command) {
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
