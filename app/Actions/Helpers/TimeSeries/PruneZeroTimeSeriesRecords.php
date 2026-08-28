<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TimeSeries;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One off cleanup of the all zero rows the time series processors used to write for periods with no
 * activity. Deletes by id range so each batch uses the primary key instead of rescanning the partition.
 */
class PruneZeroTimeSeriesRecords
{
    use AsAction;

    public string $commandSignature = 'time-series:prune_zero_records {--t|table= : Single records table, defaults to all sparse ones} {--f|frequencies=D,W,M : Frequency letters to prune} {--c|chunk=100000 : Ids scanned per statement} {--d|dry-run : Count instead of delete}';

    private const array TABLES = [
        'org_stock_time_series_records',
        'stock_time_series_records',
        'trade_unit_time_series_records',
        'asset_time_series_records',
        'master_asset_time_series_records',
        'org_stock_family_time_series_records',
        'trade_unit_family_time_series_records',
        'stock_family_time_series_records',
        'offer_time_series_records',
    ];

    private const array NUMERIC_TYPES = ['int2', 'int4', 'int8', 'numeric', 'float4', 'float8'];

    public function handle(string $table, string $frequency, int $chunk, bool $dryRun): int
    {
        $condition = $this->zeroCondition($table);

        $bounds = DB::table($table)->where('frequency', $frequency)->selectRaw('min(id) as min_id, max(id) as max_id')->first();

        if (!$bounds || $bounds->min_id === null) {
            return 0;
        }

        $affected = 0;

        for ($id = (int) $bounds->min_id; $id <= (int) $bounds->max_id; $id += $chunk) {
            $bindings = [$frequency, $id, $id + $chunk];

            $affected += $dryRun
                ? (int) DB::selectOne("select count(*) as total from $table where frequency = ? and id >= ? and id < ? and $condition", $bindings)->total
                : DB::delete("delete from $table where frequency = ? and id >= ? and id < ? and $condition", $bindings);
        }

        return $affected;
    }

    protected function zeroCondition(string $table): string
    {
        $metrics = collect(Schema::getColumns($table))
            ->filter(fn (array $column) => in_array($column['type_name'], self::NUMERIC_TYPES))
            ->pluck('name')
            ->reject(fn (string $name) => $name === 'id' || str_ends_with($name, '_id'))
            ->map(fn (string $name) => "coalesce(\"$name\", 0) = 0");

        if ($metrics->isEmpty()) {
            throw new \RuntimeException("No metric columns found on $table");
        }

        return $metrics->implode(' and ');
    }

    public function asCommand(Command $command): int
    {
        $tables = $command->option('table') ? [$command->option('table')] : self::TABLES;
        $chunk  = (int) $command->option('chunk');
        $dryRun = (bool) $command->option('dry-run');

        foreach ($tables as $table) {
            foreach (explode(',', $command->option('frequencies')) as $frequency) {
                $affected = $this->handle($table, trim($frequency), $chunk, $dryRun);

                $command->info(sprintf('%s [%s] %s %s rows', $table, trim($frequency), $dryRun ? 'would prune' : 'pruned', number_format($affected)));
            }
        }

        if (!$dryRun) {
            $command->warn('Deleted rows leave the tables bloated, run VACUUM (or VACUUM FULL for the disk back) on the pruned partitions.');
        }

        return 0;
    }
}
