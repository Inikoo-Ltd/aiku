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
    protected int $chunkSize = 5000;

    public string $commandSignature = 'invoice-transactions:repair-master-asset-id
        {--dry-run : Show how many rows would be updated without writing}
        {--from= : Only repair transactions with date >= this (YYYY-MM-DD)}
        {--to= : Only repair transactions with date <= this (YYYY-MM-DD)}
        {--async : Dispatch to queue (Horizon) instead of running inline}';

    /**
     * Build base candidate query
     */
    protected function baseQuery(?string $from, ?string $to)
    {
        $query = DB::table('invoice_transactions as it')
            ->whereNull('it.master_asset_id')
            ->whereNotNull('it.asset_id')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('assets as a')
                    ->whereColumn('a.id', 'it.asset_id')
                    ->whereNotNull('a.master_asset_id');
            });

        if ($from) {
            $query->where('it.date', '>=', $from . ' 00:00:00');
        }

        if ($to) {
            $query->where('it.date', '<=', $to . ' 23:59:59');
        }

        return $query;
    }

    /**
     * Execute repair
     */
    public function handle(?string $from = null, ?string $to = null, bool $dryRun = false, ?callable $onProgress = null): int
    {
        $baseQuery = $this->baseQuery($from, $to);
        $candidateCount = (clone $baseQuery)->count();

        if ($onProgress) {
            $onProgress(0, $candidateCount);
        }

        if ($candidateCount === 0 || $dryRun) {
            return Command::SUCCESS;
        }

        $updatedTotal = 0;
        $driver = DB::getDriverName();

        while (true) {
            $ids = (clone $baseQuery)
                ->select('it.id')
                ->orderBy('it.id')
                ->limit($this->chunkSize)
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                break;
            }

            if ($driver === 'pgsql') {
                // PostgreSQL: UPDATE ... FROM
                $idsPlaceholder = implode(',', $ids);

                $sql = "
                    UPDATE invoice_transactions it
                    SET master_asset_id = a.master_asset_id
                    FROM assets a
                    WHERE a.id = it.asset_id
                      AND it.id IN ($idsPlaceholder)
                      AND it.master_asset_id IS NULL
                      AND a.master_asset_id IS NOT NULL
                ";

                $updated = DB::affectingStatement($sql);
            } else {
                // MySQL / MariaDB: JOIN update
                $updated = DB::table('invoice_transactions as it')
                    ->join('assets as a', 'a.id', '=', 'it.asset_id')
                    ->whereIn('it.id', $ids)
                    ->whereNull('it.master_asset_id')
                    ->whereNotNull('a.master_asset_id')
                    ->update([
                        'it.master_asset_id' => DB::raw('a.master_asset_id'),
                    ]);
            }

            $updatedTotal += (int) $updated;

            if ($onProgress) {
                $onProgress($updatedTotal, $candidateCount);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * CLI handler
     */
    public function asCommand(Command $command): int
    {
        $dryRun = (bool) $command->option('dry-run');
        $from = $command->option('from');
        $to = $command->option('to');
        $async = (bool) $command->option('async');

        $baseQuery = $this->baseQuery($from, $to);
        $candidateCount = (clone $baseQuery)->count();

        $command->line('Repair invoice_transactions.master_asset_id from assets.master_asset_id');
        $command->line('Candidates: ' . number_format($candidateCount));

        if ($candidateCount === 0) {
            $command->info('Nothing to repair.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $sample = (clone $baseQuery)
                ->join('assets as a', 'a.id', '=', 'it.asset_id')
                ->select([
                    'it.id as invoice_transaction_id',
                    'it.asset_id',
                    'it.master_asset_id as current_master_asset_id',
                    'a.master_asset_id as new_master_asset_id',
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
            self::dispatch($from, $to, false)->onQueue($this->jobQueue);
            $command->info('Dispatched job to queue: ' . $this->jobQueue);
            return Command::SUCCESS;
        }

        $bar = $command->getOutput()->createProgressBar($candidateCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        try {
            $this->handle($from, $to, false, function ($current) use ($bar) {
                $bar->setProgress($current);
            });

            $bar->finish();
            $command->newLine();
            $command->info('Done.');

        } catch (Throwable $e) {
            $command->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
