<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Finishes the retirement started by AromaRetireQuantityVariants. Those products were only hidden
 * because aurora could still be holding them in an open order, so this reads the retire_at_cutover
 * flag rather than aurora, and is meant to run once aurora no longer takes orders.
 */
class AromaDiscontinueRetiredVariants
{
    use AsAction;

    public string $commandSignature = 'aroma:discontinue_retired_variants {--live : Actually write, otherwise dry run}';

    public function asCommand(Command $command): int
    {
        ini_set('memory_limit', '4G');
        DB::disableQueryLog();

        $live = (bool)$command->option('live');
        $shop = Shop::where('code', 'AROMA')->firstOrFail();

        $flagged = Product::where('shop_id', $shop->id)
            ->whereRaw("data->>'retire_at_cutover' = 'true'")
            ->orderBy('id')
            ->get();

        if ($flagged->isEmpty()) {
            $command->warn('Nothing flagged, run aroma:retire_quantity_variants first');

            return 1;
        }

        $openOrders = $this->openOrderCounts($flagged->pluck('id')->all());
        if ($openOrders) {
            $command->warn(sprintf(
                '%d flagged products still sit in %d open aiku orders, they stay sellable until those close',
                count($openOrders),
                array_sum($openOrders)
            ));
        }

        $stats = [
            'flagged'              => $flagged->count(),
            'discontinued'         => 0,
            'already_discontinued' => 0,
            'skipped_open_order'   => 0,
            'skipped_visible'      => 0,
            'skipped_keep_gone'    => 0,
        ];

        $bar = $command->getOutput()->createProgressBar($flagged->count());
        $bar->setFormat(" %current%/%max% products [%bar%] %percent:3s%%  elapsed %elapsed:6s%  left %estimated:-6s%");
        $bar->start();

        foreach ($flagged as $product) {
            $bar->advance();

            if ($product->state == ProductStateEnum::DISCONTINUED) {
                $stats['already_discontinued']++;
                continue;
            }

            // Something put it back on sale after it was flagged, that is a deliberate act by a
            // human or a fetch and this command will not undo it silently
            if ($product->is_main || $product->is_for_sale) {
                $stats['skipped_visible']++;
                $this->detail($command, "skip, visible again: $product->code", 'warn');
                continue;
            }

            if (Arr::get($openOrders, $product->id, 0) > 0) {
                $stats['skipped_open_order']++;
                $this->detail($command, "skip, in an open order: $product->code", 'warn');
                continue;
            }

            $keepId = Arr::get($product->data, 'replaced_by_product_id');
            $keep   = $keepId ? Product::find($keepId) : null;
            if (!$keep || !$keep->is_for_sale) {
                $stats['skipped_keep_gone']++;
                $this->detail($command, "skip, replacement is not on sale: $product->code", 'warn');
                continue;
            }

            $stats['discontinued']++;
            $this->detail($command, "discontinue: $product->code (replaced by $keep->code)");
            if ($live) {
                UpdateProduct::make()->action($product, [
                    'status' => ProductStatusEnum::DISCONTINUED,
                    'state'  => ProductStateEnum::DISCONTINUED,
                ]);
            }
        }

        $bar->finish();
        $command->newLine(2);

        $command->table(array_keys($stats), [array_values($stats)]);
        $command->info($live ? 'LIVE run done' : 'Dry run, nothing written. Use --live to apply.');

        return 0;
    }

    /**
     * @param  array<int>  $productIds
     * @return array<int, int>
     */
    private function openOrderCounts(array $productIds): array
    {
        $counts = [];
        foreach (array_chunk($productIds, 1000) as $chunk) {
            $rows = DB::table('transactions as t')
                ->join('orders as o', 'o.id', 't.order_id')
                ->whereIn('t.model_id', $chunk)
                ->where('t.model_type', 'Product')
                // 'creating' is an unsubmitted basket, thousands sit abandoned since 2021 and
                // would block the cutover forever. Only committed orders hold a product back.
                ->whereIn('o.state', ['submitted', 'handling', 'handling_blocked', 'packing', 'packed', 'picked', 'in_warehouse'])
                ->selectRaw('t.model_id, count(distinct o.id) c')
                ->groupBy('t.model_id')
                ->get();
            foreach ($rows as $row) {
                $counts[$row->model_id] = (int)$row->c;
            }
        }

        return $counts;
    }

    private function detail(Command $command, string $message, ?string $style = null): void
    {
        if (!$command->getOutput()->isVerbose()) {
            return;
        }

        if ($style === 'warn') {
            $command->warn($message);

            return;
        }

        $command->line($message);
    }
}
