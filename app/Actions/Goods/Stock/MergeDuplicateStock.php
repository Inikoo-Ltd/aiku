<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock;

use App\Actions\Catalogue\Product\SyncProductOrgStocksFromTradeUnits;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Points a stock's trade units, masters and products at the duplicate that actually
 * holds the goods, then retires the empty one and every org stock left orphaned by it.
 *
 * The duplicates come in pairs: a newer, correctly named stock nobody ever booked
 * inventory into, and an older one holding every unit and every movement, usually
 * carrying "-error" or "-deleted" in its code. Picking follows the named one and finds
 * nothing. The goods never move — only the links do — so the surviving record is always
 * the one with the movements.
 */
class MergeDuplicateStock
{
    use AsAction;

    public string $commandSignature = 'stocks:merge-duplicate
        {--from= : Code of the empty stock being retired}
        {--to= : Code of the stock holding the goods, which survives}
        {--rename= : Code to give the surviving stock, freed by renaming the retired one}
        {--dry-run : Show the plan without writing}';

    /**
     * @return array{from: Stock, to: Stock, products: \Illuminate\Support\Collection<int, Product>, orphans: \Illuminate\Support\Collection<int, OrgStock>, trade_units: int, masters: int}
     */
    public function plan(Stock $from, Stock $to): array
    {
        $orphans = OrgStock::where('stock_id', $from->id)
            ->whereDoesntHave('locationOrgStocks', fn ($query) => $query->where('quantity', '>', 0))
            ->get();

        $products = Product::whereHas('orgStocks', fn ($query) => $query->where('org_stocks.stock_id', $from->id))->get();

        return [
            'from'        => $from,
            'to'          => $to,
            'products'    => $products,
            'orphans'     => $orphans,
            'trade_units' => DB::table('model_has_trade_units')->where('model_type', 'Stock')->where('model_id', $from->id)->count(),
            'masters'     => DB::table('master_asset_has_stocks')->where('stock_id', $from->id)->count(),
        ];
    }

    /**
     * @throws \Throwable
     */
    public function handle(Stock $from, Stock $to, ?string $rename = null): array
    {
        $plan = $this->plan($from, $to);

        DB::transaction(function () use ($from, $to, $plan, $rename) {
            /* Trade unit and master links move first: the product re-sync below reads them. */
            DB::table('model_has_trade_units')
                ->where('model_type', 'Stock')
                ->where('model_id', $from->id)
                ->update(['model_id' => $to->id]);

            DB::table('master_asset_has_stocks')
                ->where('stock_id', $from->id)
                ->update(['stock_id' => $to->id]);

            if ($rename) {
                /*
                 * Slugs are set by hand: the model does not regenerate them on update, so a
                 * rename alone would leave the survivor answering on the retired code's slug
                 * and keep "-error" in its URL, which is the whole point of the rename.
                 */
                $retiredCode = $from->code.'-merged';
                $from->update(['code' => $retiredCode, 'slug' => Str::slug($retiredCode)]);
                $to->update(['code' => $rename, 'slug' => Str::slug($rename), 'state' => StockStateEnum::ACTIVE]);
            }

            $from->update(['state' => StockStateEnum::DISCONTINUED]);

            /*
             * Orphans are retired rather than deleted: they carry the history of what was
             * picked against them, and nothing sells from a discontinued org stock.
             */
            OrgStock::whereIn('id', $plan['orphans']->pluck('id'))
                ->update(['state' => OrgStockStateEnum::DISCONTINUED]);
        });

        foreach ($plan['products'] as $product) {
            SyncProductOrgStocksFromTradeUnits::run($product->refresh());
        }

        return $plan;
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $from = Stock::where('code', $command->option('from'))->first();
        $to   = Stock::where('code', $command->option('to'))->first();

        if (!$from || !$to) {
            $command->error('Both --from and --to must name an existing stock.');

            return Command::FAILURE;
        }

        if ($from->id === $to->id) {
            $command->error('--from and --to are the same stock.');

            return Command::FAILURE;
        }

        $plan     = $this->plan($from, $to);
        $fromHeld = $from->orgStocks()->withSum('locationOrgStocks as held', 'quantity')->get()->sum('held');
        $toHeld   = $to->orgStocks()->withSum('locationOrgStocks as held', 'quantity')->get()->sum('held');

        $command->table(['', 'stock', 'code', 'state', 'units held'], [
            ['retire ', $from->id, $from->code, $from->state->value, $fromHeld],
            ['survive', $to->id, $to->code, $to->state->value, $toHeld],
        ]);

        if ($fromHeld > 0) {
            $command->error("Refusing: $from->code still holds $fromHeld units. Move the stock before merging.");

            return Command::FAILURE;
        }

        $command->line('Trade unit links to move: '.$plan['trade_units']);
        $command->line('Master links to move:     '.$plan['masters']);
        $command->line('Products to re-sync:      '.$plan['products']->count());
        $command->line('Org stocks to retire:     '.$plan['orphans']->count().' ('.$plan['orphans']->pluck('code')->implode(', ').')');

        if ($rename = $command->option('rename')) {
            $command->line("Rename: $to->code -> $rename, and $from->code -> {$from->code}-merged");
        }

        if ($command->option('dry-run')) {
            $command->info('Dry run only. No changes were written.');

            return Command::SUCCESS;
        }

        $this->handle($from, $to, $rename);
        $command->info('Merged.');

        return Command::SUCCESS;
    }
}
