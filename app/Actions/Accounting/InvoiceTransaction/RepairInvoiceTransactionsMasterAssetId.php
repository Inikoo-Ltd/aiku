<?php

namespace App\Actions\Accounting\InvoiceTransaction;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairInvoiceTransactionsMasterAssetId implements ShouldQueue
{
    use AsAction;

    public string $jobQueue = 'default-long';

    public string $commandSignature = 'invoice-transactions:repair-master-asset-id
        {--dry-run : Show how many rows would be updated without writing}
        {--chunk=5000 : Chunk size for batch updates}
        {--from= : Only repair transactions with date >= this (YYYY-MM-DD)}
        {--to= : Only repair transactions with date <= this (YYYY-MM-DD)}
        {--force : Skip confirmation prompt}
        {--async : Dispatch to queue (Horizon) instead of running inline}';

    public function handle(?string $from = null, ?string $to = null, int $chunkSize = 5000, bool $dryRun = false, bool $force = false): int
    {
        if ($chunkSize < 1) {
            return Command::FAILURE;
        }

        $baseQuery = DB::table('invoice_transactions as it')
            ->whereNull('it.master_asset_id')
            ->whereNotNull('it.asset_id')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('assets as a')
                    ->whereColumn('a.id', 'it.asset_id')
                    ->whereNotNull('a.master_asset_id');
            });

        if ($from !== null && $from !== '') {
            $baseQuery->where('it.date', '>=', $from.' 00:00:00');
        }

        if ($to !== null && $to !== '') {
            $baseQuery->where('it.date', '<=', $to.' 23:59:59');
        }

        $candidateCount = (clone $baseQuery)->count();

        if ($candidateCount === 0) {
            return Command::SUCCESS;
        }

        if ($dryRun) {
            return Command::SUCCESS;
        }

        $updatedTotal = 0;

        while (true) {
            $ids = (clone $baseQuery)
                ->select('it.id')
                ->orderBy('it.id')
                ->limit($chunkSize)
                ->pluck('id')
                ->all();

            if (count($ids) === 0) {
                break;
            }

            $updated = DB::table('invoice_transactions as it')
                ->whereIn('it.id', $ids)
                ->whereNull('it.master_asset_id')
                ->whereNotNull('it.asset_id')
                ->whereExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('assets as a')
                        ->whereColumn('a.id', 'it.asset_id')
                        ->whereNotNull('a.master_asset_id');
                })
                ->update([
                    'master_asset_id' => DB::raw('(select a.master_asset_id from assets as a where a.id = it.asset_id)'),
                ]);

            $updatedTotal += (int) $updated;
        }

        return $updatedTotal > 0 ? Command::SUCCESS : Command::FAILURE;
    }

    public function asCommand(Command $command): int
    {
        $dryRun = (bool) $command->option('dry-run');
        $chunkSize = (int) $command->option('chunk');
        $from = $command->option('from');
        $to = $command->option('to');
        $force = (bool) $command->option('force');
        $async = (bool) $command->option('async');

        if ($chunkSize < 1) {
            $command->error('Option --chunk must be >= 1');

            return Command::FAILURE;
        }

        $baseQuery = DB::table('invoice_transactions as it')
            ->whereNull('it.master_asset_id')
            ->whereNotNull('it.asset_id')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('assets as a')
                    ->whereColumn('a.id', 'it.asset_id')
                    ->whereNotNull('a.master_asset_id');
            });

        if ($from !== null && $from !== '') {
            $baseQuery->where('it.date', '>=', $from.' 00:00:00');
        }

        if ($to !== null && $to !== '') {
            $baseQuery->where('it.date', '<=', $to.' 23:59:59');
        }

        $candidateCount = (clone $baseQuery)->count();

        $command->line('Repair invoice_transactions.master_asset_id from assets.master_asset_id');
        $command->line('Candidates: '.$candidateCount);

        if ($candidateCount === 0) {
            $command->info('Nothing to repair.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $sample = (clone $baseQuery)
                ->select([
                    'it.id as invoice_transaction_id',
                    'it.asset_id',
                    'it.master_asset_id as current_master_asset_id',
                    DB::raw('(select a.master_asset_id from assets as a where a.id = it.asset_id) as new_master_asset_id'),
                    'it.date',
                ])
                ->orderBy('it.id')
                ->limit(10)
                ->get();

            $command->table(
                ['invoice_transaction_id', 'asset_id', 'current_master_asset_id', 'new_master_asset_id', 'date'],
                $sample->map(fn ($row) => [
                    $row->invoice_transaction_id,
                    $row->asset_id,
                    $row->current_master_asset_id,
                    $row->new_master_asset_id,
                    $row->date,
                ])->all()
            );

            $command->info('Dry run only. No changes were written.');

            return Command::SUCCESS;
        }

        if ($async) {
            self::dispatch($from, $to, $chunkSize, false, $force)->onQueue($this->jobQueue);

            $command->info('Dispatched job to queue: '.$this->jobQueue);

            return Command::SUCCESS;
        }

        if (!$force) {
            if (!$command->confirm('Proceed to update '.$candidateCount.' invoice_transactions rows?', true)) {
                $command->info('Aborted.');

                return Command::SUCCESS;
            }
        }

        $updatedTotal = 0;

        try {
            while (true) {
                $ids = (clone $baseQuery)
                    ->select('it.id')
                    ->orderBy('it.id')
                    ->limit($chunkSize)
                    ->pluck('id')
                    ->all();

                if (count($ids) === 0) {
                    break;
                }

                $updated = DB::table('invoice_transactions as it')
                    ->whereIn('it.id', $ids)
                    ->whereNull('it.master_asset_id')
                    ->whereNotNull('it.asset_id')
                    ->whereExists(function ($query) {
                        $query->selectRaw('1')
                            ->from('assets as a')
                            ->whereColumn('a.id', 'it.asset_id')
                            ->whereNotNull('a.master_asset_id');
                    })
                    ->update([
                        'master_asset_id' => DB::raw('(select a.master_asset_id from assets as a where a.id = it.asset_id)'),
                    ]);

                $updatedTotal += (int) $updated;

                $command->line('Updated '.$updated.' rows (total '.$updatedTotal.')');
            }
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return Command::FAILURE;
        }

        $command->info('Done. Updated total rows: '.$updatedTotal);

        return Command::SUCCESS;
    }
}
