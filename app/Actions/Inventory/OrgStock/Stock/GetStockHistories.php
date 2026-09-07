<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Actions\Traits\WithStockHistoryArchiveWrite;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The one entry point for ad hoc analysis over stock history, so a question asked across years
 * does not silently answer from the last three. Beyond the retention window the operational
 * database keeps only one snapshot per month; the rest of those days are in the archive.
 *
 * The whole query is expressed once and run on both databases, because the archive carries the
 * same tables and mirrors org_stocks and locations, so joins behave identically there:
 *
 *   GetStockHistories::run(fn ($query) => $query
 *       ->join('org_stocks', 'org_stocks.id', '=', 'org_stock_histories.org_stock_id')
 *       ->where('org_stocks.code', 'ARO-1234')
 *       ->whereBetween('org_stock_histories.date', ['2021-12-01', '2021-12-31'])
 *       ->select(['org_stock_histories.date', 'org_stock_histories.wac_per_sku']));
 *
 * A date lives entirely on one side, so the two result sets never overlap and are simply
 * concatenated, sorted by date when the rows carry one.
 */
class GetStockHistories
{
    use AsAction;
    use WithStockHistoryArchiveWrite;

    /**
     * @param Closure(Builder): Builder $query built against org_stock_histories unless $table says otherwise
     */
    public function handle(Closure $query, string $table = 'org_stock_histories'): Collection
    {
        $rows = collect();

        foreach ($this->stockHistoryWriteConnections() as $connection) {
            $rows = $rows->concat($query(DB::connection($connection)->table($table))->get());
        }

        return isset($rows->first()->date)
            ? $rows->sortBy('date')->values()
            : $rows->values();
    }

    public function getCommandSignature(): string
    {
        return 'org_stock:history {orgStock : OrgStock ID} {--from=} {--until=}';
    }

    public function asCommand(Command $command): int
    {
        $rows = $this->handle(fn (Builder $query) => $query
            ->where('org_stock_id', (int) $command->argument('orgStock'))
            ->when($command->option('from'), fn ($builder) => $builder->where('date', '>=', $command->option('from')))
            ->when($command->option('until'), fn ($builder) => $builder->where('date', '<=', $command->option('until')))
            ->select(['date', 'quantity_in_locations', 'lpp_per_sku', 'wac_per_sku', 'fifo_per_sku', 'org_stock_lpp_value', 'org_stock_wac_value', 'org_stock_fifo_value']));

        $command->table(
            ['date', 'quantity', 'lpp/sku', 'wac/sku', 'fifo/sku', 'lpp value', 'wac value', 'fifo value'],
            $rows->map(fn ($row) => (array) $row)->all()
        );

        return 0;
    }
}
