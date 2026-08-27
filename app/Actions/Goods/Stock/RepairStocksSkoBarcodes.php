<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock;

use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
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

    /**
     * Written as one mass update on purpose, unlike the cascade in UpdateOrgStock: this is a data
     * migration carrying existing barcodes to where they now belong, not somebody changing them,
     * so it stays out of the per org stock history rather than filling it with a repair nobody
     * made a decision about.
     */
    public function handle(Stock $stock, string $barcode, bool $isHandSet): void
    {
        $stock->update(['barcode' => $barcode]);

        $stock->orgStocks()
            ->whereIn('id', static::orgStocksToCarryBarcode($stock, $barcode)->pluck('id'))
            ->update(['barcode' => $barcode, 'independent_barcode' => $isHandSet]);
    }

    /**
     * One org stock per organisation, because a scan has to name one thing on one warehouse floor
     * and the database says so too: org_stocks is unique on (organisation_id, barcode).
     *
     * Where an organisation holds the stock once, that row carries the barcode. Where it holds it
     * twice - the '-error' twins, the 'a' suffixed ones - the repair refuses to pick a winner: the
     * suffixed twin is routinely not the one being picked, and sending scans to the wrong one is
     * worse than sending none. The twin already carrying the barcode keeps it, since that is what
     * the warehouse has been scanning; if neither has one, the organisation is left out and the
     * stock reported, until somebody merges the duplicate.
     *
     * @return Collection<int, OrgStock>
     */
    public static function orgStocksToCarryBarcode(Stock $stock, ?string $barcode = null): Collection
    {
        return $stock->orgStocks()
            ->orderBy('id')
            ->get()
            ->groupBy('organisation_id')
            ->map(function (Collection $inOrganisation) use ($barcode) {
                $live = $inOrganisation->filter(fn (OrgStock $orgStock) => $orgStock->state != OrgStockStateEnum::DISCONTINUED);
                $candidates = $live->isNotEmpty() ? $live : $inOrganisation;

                if ($candidates->count() === 1) {
                    return $candidates->first();
                }

                return $barcode === null
                    ? null
                    : $candidates->first(fn (OrgStock $orgStock) => $orgStock->barcode === $barcode);
            })
            ->filter()
            ->values();
    }

    /**
     * The organisations holding this stock more than once, which is why they are sitting out of
     * the cascade. Reported so the duplicates can be merged rather than quietly ignored.
     *
     * @return Collection<int, int>
     */
    public static function organisationsWithDuplicates(Stock $stock): Collection
    {
        return $stock->orgStocks()
            ->get()
            ->groupBy('organisation_id')
            ->filter(function (Collection $inOrganisation) {
                $live = $inOrganisation->filter(fn (OrgStock $orgStock) => $orgStock->state != OrgStockStateEnum::DISCONTINUED);

                return ($live->isNotEmpty() ? $live : $inOrganisation)->count() > 1;
            })
            ->keys();
    }

    /**
     * Discontinued org stocks get no vote: nobody picks them, so a stale barcode one of them was
     * left holding must not win the ballot and be pushed onto the organisations still selling it.
     */
    public function canonicalBarcode(Stock $stock): ?string
    {
        return $stock->orgStocks()
            ->whereNotNull('barcode')
            ->where('state', '!=', OrgStockStateEnum::DISCONTINUED)
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
     * organisation still selling it, and when all org stocks that carry an EAN agree on which one
     * it is. Discontinued org stocks are left out of both tests, the same way they are left out of
     * the ballot: a pack size nobody picks any more must not veto the copy.
     */
    public function unitBarcodeToCopy(Stock $stock): ?string
    {
        $orgStocks = $stock->orgStocks()
            ->where('state', '!=', OrgStockStateEnum::DISCONTINUED)
            ->get(['unit_barcode', 'packed_in']);

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

                    /*
                     * Counted over the org stocks that will actually receive the barcode, not every
                     * org stock of the stock: a duplicate inside an organisation is never going to
                     * get one, so counting it here would leave the stock reported as needing repair
                     * on every future run, however many times it has already been repaired.
                     */
                    $siblings = static::orgStocksToCarryBarcode($stock, $barcode)
                        ->filter(fn (OrgStock $orgStock) => $orgStock->barcode !== $barcode)
                        ->count();

                    if ($stock->barcode === $barcode && $siblings === 0) {
                        continue;
                    }

                    if ($apply) {
                        $this->handle($stock, $barcode, true);
                    } else {
                        $command->line("$stock->code -> $barcode ($siblings org stocks to align)");
                    }

                    foreach (static::organisationsWithDuplicates($stock) as $organisationId) {
                        $command->warn("$stock->code: organisation $organisationId holds it twice, left without a barcode");
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
