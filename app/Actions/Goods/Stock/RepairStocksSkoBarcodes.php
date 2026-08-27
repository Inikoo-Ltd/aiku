<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock;

use App\Enums\Goods\Stock\StockStateEnum;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One-off repair for the era when SKO barcodes were edited per org stock, in two phases. First it
 * pushes each org stock barcode up to its stock and back down to every sibling, the write-through
 * UpdateOrgStock does now on every edit; where siblings disagree the most common value wins, ties
 * broken by the oldest org stock (the organisation the stock was born in). Then, for stocks still
 * without a SKO barcode whose org stocks are all packed in ones, it copies the unit EAN up: the
 * outer and the unit are the same physical thing there, so the EAN is the scannable SKO barcode.
 * Discontinued stocks are left out: nobody picks them, and an EAN parked on one would block the
 * live stock that answers to it. New products keep needing their SKO barcode set by hand.
 *
 * Either phase leaves a stock alone (and reports it) when the barcode it would write is already
 * held by a different stock in the same group: a scan has to name exactly one stock.
 */
class RepairStocksSkoBarcodes
{
    use AsAction;

    public string $commandSignature = 'stocks:repair_sko_barcodes
        {--apply : Write the barcodes, without this the command only reports what it would do}';

    public function handle(Stock $stock, string $barcode, bool $isHandSet): void
    {
        $stock->update(['barcode' => $barcode]);
        $stock->orgStocks()->update(['barcode' => $barcode, 'independent_barcode' => $isHandSet]);
    }

    public function canonicalBarcode(Stock $stock): ?string
    {
        return $stock->orgStocks()
            ->whereNotNull('barcode')
            ->select('barcode', DB::raw('count(*) as uses'), DB::raw('min(id) as oldest_id'))
            ->groupBy('barcode')
            ->orderByDesc('uses')
            ->orderBy('oldest_id')
            ->value('barcode');
    }

    /**
     * Names whoever else in the group already answers to this barcode, or null when nobody does.
     * An org stock with no stock of its own is a conflict like any other, so the row itself is
     * fetched rather than its stock_id: a null stock_id there means orphan, never 'no conflict'.
     */
    public function conflictingHolder(Stock $stock, string $barcode): ?string
    {
        $orgStock = OrgStock::where('group_id', $stock->group_id)
            ->where('barcode', $barcode)
            ->where(fn ($query) => $query->whereNull('stock_id')->orWhere('stock_id', '!=', $stock->id))
            ->first(['id', 'stock_id']);

        if ($orgStock) {
            return $orgStock->stock_id ? "stock id $orgStock->stock_id" : "org stock id $orgStock->id";
        }

        $conflictingStockId = Stock::where('group_id', $stock->group_id)
            ->where('barcode', $barcode)
            ->whereKeyNot($stock->id)
            ->value('id');

        return $conflictingStockId ? "stock id $conflictingStockId" : null;
    }

    /**
     * The unit EAN is only safe as the SKO barcode when the outer holds exactly one unit in every
     * organisation, and when all org stocks that carry an EAN agree on which one it is.
     */
    public function unitBarcodeToCopy(Stock $stock): ?string
    {
        $orgStocks = $stock->orgStocks()->get(['unit_barcode', 'packed_in']);

        if ($orgStocks->contains(fn (OrgStock $orgStock) => ($orgStock->packed_in ?? 1) > 1)) {
            return null;
        }

        $unitBarcodes = $orgStocks->pluck('unit_barcode')->filter()->unique();

        return $unitBarcodes->count() === 1 ? $unitBarcodes->first() : null;
    }

    public function asCommand(Command $command): int
    {
        $apply    = $command->option('apply');
        $repaired = 0;
        $filled   = 0;

        Stock::whereHas('orgStocks', fn ($query) => $query->whereNotNull('barcode'))
            ->chunkById(500, function ($stocks) use ($command, $apply, &$repaired) {
                foreach ($stocks as $stock) {
                    $barcode = $this->canonicalBarcode($stock);

                    if (!$barcode) {
                        continue;
                    }

                    if ($conflict = $this->conflictingHolder($stock, $barcode)) {
                        $command->warn("$stock->code: $barcode also on $conflict, skipped");
                        continue;
                    }

                    $siblings = $stock->orgStocks()->where(
                        fn ($query) => $query->whereNull('barcode')->orWhere('barcode', '!=', $barcode)
                    )->count();

                    if ($stock->barcode === $barcode && $siblings === 0) {
                        continue;
                    }

                    if ($apply) {
                        $this->handle($stock, $barcode, true);
                    } else {
                        $command->line("$stock->code -> $barcode ($siblings org stocks to align)");
                    }

                    $repaired++;
                }
            });

        Stock::whereNull('barcode')
            ->where('state', '!=', StockStateEnum::DISCONTINUED)
            ->whereDoesntHave('orgStocks', fn ($query) => $query->whereNotNull('barcode'))
            ->whereHas('orgStocks', fn ($query) => $query->whereNotNull('unit_barcode'))
            ->chunkById(500, function ($stocks) use ($command, $apply, &$filled) {
                foreach ($stocks as $stock) {
                    $barcode = $this->unitBarcodeToCopy($stock);

                    if (!$barcode) {
                        continue;
                    }

                    if ($conflict = $this->conflictingHolder($stock, $barcode)) {
                        $command->warn("$stock->code: unit EAN $barcode also on $conflict, skipped");
                        continue;
                    }

                    if ($apply) {
                        $this->handle($stock, $barcode, false);
                    } else {
                        $command->line("$stock->code -> $barcode (from unit EAN)");
                    }

                    $filled++;
                }
            });

        $command->info(
            $apply
                ? "$repaired stocks repaired, $filled filled from their unit EAN"
                : "$repaired stocks would be repaired and $filled filled from their unit EAN, run with --apply"
        );

        return 0;
    }
}
