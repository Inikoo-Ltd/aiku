<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity;
use App\Actions\Catalogue\Product\SyncProductOrgStocksFromTradeUnits;
use App\Models\Goods\TradeUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Moves every link (stocks, org stocks, products, masters, supplier products) from a duplicate
 * trade unit to its twin, retires the duplicate and re-syncs the twin's products so they find
 * the warehouse stock again.
 *
 * The pair appears when a trade unit is created by hand moments before the Aurora fetch creates
 * the same one by source id: products end up on one twin and the stock on the other, so the
 * product has no org stock and shows as coming soon while the warehouse holds units.
 */
class MergeDuplicateTradeUnit
{
    use AsAction;

    public string $commandSignature = 'trade_units:merge-duplicate
        {--from= : Id of the duplicate trade unit being retired}
        {--to= : Id of the trade unit that survives}
        {--dry-run : Show the plan without writing}';

    /**
     * @return array{links: \Illuminate\Support\Collection<int, object>, kept: \Illuminate\Support\Collection<int, object>}
     */
    public function plan(TradeUnit $from, TradeUnit $to): array
    {
        $links = DB::table('model_has_trade_units')->where('trade_unit_id', $from->id)->get();
        $kept  = DB::table('model_has_trade_units')->where('trade_unit_id', $to->id)->get();

        return [
            'links' => $links->reject(fn ($link) => $kept->contains(fn ($keep) => $keep->model_type === $link->model_type && $keep->model_id === $link->model_id)),
            'kept'  => $links->filter(fn ($link) => $kept->contains(fn ($keep) => $keep->model_type === $link->model_type && $keep->model_id === $link->model_id)),
        ];
    }

    /**
     * @throws \Throwable
     */
    public function handle(TradeUnit $from, TradeUnit $to): array
    {
        $plan = $this->plan($from, $to);

        DB::transaction(function () use ($from, $to, $plan) {
            DB::table('model_has_trade_units')
                ->whereIn('id', $plan['kept']->pluck('id'))
                ->delete();
            DB::table('model_has_trade_units')
                ->where('trade_unit_id', $from->id)
                ->update(['trade_unit_id' => $to->id]);

            $deleted = DeleteTradeUnit::run($from, ['force' => true, 'reason' => "merged into trade unit $to->id"]);
            if (!$deleted['ok']) {
                throw new \RuntimeException($deleted['error'] ?? 'Could not retire the duplicate trade unit');
            }
        });

        foreach ($to->products()->get() as $product) {
            ProductHydrateAvailableQuantity::run(SyncProductOrgStocksFromTradeUnits::run($product));
        }

        return $plan;
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $from = TradeUnit::find((int) $command->option('from'));
        $to   = TradeUnit::find((int) $command->option('to'));

        if (!$from || !$to || $from->id === $to->id) {
            $command->error('--from and --to must be two different existing trade unit ids.');

            return Command::FAILURE;
        }

        if ($from->code !== $to->code) {
            $command->error("Refusing: $from->code and $to->code are not the same trade unit.");

            return Command::FAILURE;
        }

        $plan = $this->plan($from, $to);

        $command->table(['', 'id', 'code', 'status', 'links'], [
            ['retire ', $from->id, $from->code, $from->status->value, $plan['links']->count() + $plan['kept']->count()],
            ['survive', $to->id, $to->code, $to->status->value, DB::table('model_has_trade_units')->where('trade_unit_id', $to->id)->count()],
        ]);
        $command->line('Links to move: '.$plan['links']->map(fn ($link) => "$link->model_type:$link->model_id")->implode(', '));
        $command->line('Links already on survivor, dropped: '.$plan['kept']->map(fn ($link) => "$link->model_type:$link->model_id")->implode(', '));

        if ($command->option('dry-run')) {
            $command->info('Dry run only. No changes were written.');

            return Command::SUCCESS;
        }

        $this->handle($from, $to);
        $command->info('Merged.');

        return Command::SUCCESS;
    }
}
