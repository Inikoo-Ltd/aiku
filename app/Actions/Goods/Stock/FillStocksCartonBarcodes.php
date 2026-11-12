<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock;

use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One-off fill of the empty carton barcodes with the convention most cartons already follow: the
 * SKO barcode plus an uppercase "C", or, for stocks without a SKO barcode, the unit EAN their live
 * org stocks agree on plus "C". Discontinued stocks are left out and a carton already set is never
 * touched, so staff can still change or clear it from the Barcodes card afterwards. A cleared
 * carton is filled again if the command is run again.
 *
 * A carton is skipped (and reported) when it is already a carton or SKO barcode of another stock
 * in the group, including one filled earlier in the same run: a scan has to name exactly one stock.
 * Stocks with a SKO barcode go first, so a stock borrowing a unit EAN never takes the carton of a
 * stock that has a SKO barcode of its own. Every write is listed, so --apply output is the record of
 * what changed: carton_barcode is not audited.
 */
class FillStocksCartonBarcodes
{
    use AsAction;

    public const string SUFFIX = 'C';

    public string $commandSignature = 'stocks:fill_carton_barcodes
        {--apply : Write the carton barcodes, without this the command only reports what it would do}';

    /** @var array<string, int> */
    private array $cartonsFilledThisRun = [];

    public function handle(Stock $stock, string $cartonBarcode): bool
    {
        return Stock::whereKey($stock->id)
            ->where(fn ($query) => $query->whereNull('carton_barcode')->orWhere('carton_barcode', ''))
            ->update(['carton_barcode' => $cartonBarcode]) === 1;
    }

    /**
     * @return array{carton: ?string, source: ?string, skip: ?string}
     */
    public function cartonFor(Stock $stock): array
    {
        if (!blank($stock->carton_barcode)) {
            return ['carton' => null, 'source' => null, 'skip' => null];
        }

        if (!blank($stock->barcode)) {
            $base   = trim($stock->barcode);
            $source = 'SKO';
        } else {
            $unitBarcodes = $stock->orgStocks()
                ->where('state', '!=', OrgStockStateEnum::DISCONTINUED)
                ->pluck('unit_barcode')
                ->map(fn (?string $unitBarcode) => blank($unitBarcode) ? null : trim($unitBarcode))
                ->filter()
                ->unique();

            if ($unitBarcodes->count() > 1) {
                return ['carton' => null, 'source' => 'unit', 'skip' => 'organisations disagree on the unit EAN ('.$unitBarcodes->implode(', ').')'];
            }

            if ($unitBarcodes->isEmpty()) {
                return ['carton' => null, 'source' => null, 'skip' => null];
            }

            $base   = $unitBarcodes->first();
            $source = 'unit';
        }

        $carton = $base.self::SUFFIX;

        if (strlen($carton) > 64) {
            return ['carton' => null, 'source' => $source, 'skip' => "$carton is longer than 64 characters"];
        }

        if ($conflict = $this->conflictingHolder($stock, $carton)) {
            return ['carton' => null, 'source' => $source, 'skip' => "$carton already used by $conflict"];
        }

        return ['carton' => $carton, 'source' => $source, 'skip' => null];
    }

    public function conflictingHolder(Stock $stock, string $carton): ?string
    {
        $filledEarlier = $this->cartonsFilledThisRun[$carton] ?? null;
        if ($filledEarlier !== null && $filledEarlier !== $stock->id) {
            return "stock id $filledEarlier (filled in this run)";
        }

        $otherStock = Stock::where('group_id', $stock->group_id)
            ->whereKeyNot($stock->id)
            ->where(fn ($query) => $query->where('carton_barcode', $carton)->orWhere('barcode', $carton))
            ->first(['id', 'code']);

        if ($otherStock) {
            return "stock $otherStock->code";
        }

        $orgStock = OrgStock::where('group_id', $stock->group_id)
            ->where('barcode', $carton)
            ->where(fn ($query) => $query->whereNull('stock_id')->orWhere('stock_id', '!=', $stock->id))
            ->first(['id', 'code', 'stock_id']);

        return $orgStock ? "org stock $orgStock->code" : null;
    }

    public function rememberFilled(Stock $stock, string $carton): void
    {
        $this->cartonsFilledThisRun[$carton] = $stock->id;
    }

    public function candidates(bool $withSkoBarcode): Builder
    {
        $hasSkoBarcode = fn ($query) => $query->whereNotNull('barcode')->where('barcode', '!=', '');

        return Stock::where('state', '!=', StockStateEnum::DISCONTINUED)
            ->where(fn ($query) => $query->whereNull('carton_barcode')->orWhere('carton_barcode', ''))
            ->when(
                $withSkoBarcode,
                fn ($query) => $query->where($hasSkoBarcode),
                fn ($query) => $query
                    ->whereNot($hasSkoBarcode)
                    ->whereHas(
                        'orgStocks',
                        fn ($subQuery) => $subQuery
                            ->where('state', '!=', OrgStockStateEnum::DISCONTINUED)
                            ->whereNotNull('unit_barcode')
                            ->where('unit_barcode', '!=', '')
                    )
            );
    }

    public function asCommand(Command $command): int
    {
        $apply    = $command->option('apply');
        $fromSko  = 0;
        $fromUnit = 0;
        $skipped  = [];

        foreach ([true, false] as $withSkoBarcode) {
            $this->candidates($withSkoBarcode)
                ->chunkById(500, function ($stocks) use ($apply, $command, &$fromSko, &$fromUnit, &$skipped) {
                    foreach ($stocks as $stock) {
                        $result = $this->cartonFor($stock);

                        if ($result['skip']) {
                            $skipped[] = "$stock->code: {$result['skip']}, skipped";
                            continue;
                        }

                        if (!$result['carton']) {
                            continue;
                        }

                        if ($apply && !$this->handle($stock, $result['carton'])) {
                            $skipped[] = "$stock->code: carton set meanwhile, skipped";
                            continue;
                        }

                        $this->rememberFilled($stock, $result['carton']);
                        $command->line("$stock->code -> {$result['carton']} (from {$result['source']})");

                        $result['source'] === 'SKO' ? $fromSko++ : $fromUnit++;
                    }
                });
        }

        foreach ($skipped as $message) {
            $command->warn($message);
        }

        $total = $fromSko + $fromUnit;
        $command->info(
            ($apply ? "$total cartons filled" : "$total cartons would be filled")
            ." ($fromSko from SKO, $fromUnit from unit EAN), ".count($skipped).' skipped'
            .($apply ? '' : ', run with --apply')
        );

        return 0;
    }
}
