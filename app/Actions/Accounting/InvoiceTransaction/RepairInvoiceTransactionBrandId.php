<?php

namespace App\Actions\Accounting\InvoiceTransaction;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairInvoiceTransactionBrandId implements ShouldQueue
{
    use AsAction;

    public string $jobQueue = 'default-long';
    protected int $chunkSize = 5000;

    public string $commandSignature = 'invoice-transactions:repair-brand-id
        {--dry-run : Show how many rows would be updated without writing}
        {--from= : Only repair transactions with date >= this (YYYY-MM-DD)}
        {--to= : Only repair transactions with date <= this (YYYY-MM-DD)}
        {--async : Dispatch to queue (Horizon) instead of running inline}';

    protected function baseQuery(?string $from, ?string $to)
    {
        $query = DB::table('invoice_transactions as it')
            ->whereNull('it.brand_id')
            ->where('it.model_type', 'Product')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('model_has_brands as mhb')
                    ->whereColumn('mhb.model_type', 'it.model_type')
                    ->whereColumn('mhb.model_id', 'it.model_id');
            });

        if ($from) {
            $query->where('it.date', '>=', $from . ' 00:00:00');
        }

        if ($to) {
            $query->where('it.date', '<=', $to . ' 23:59:59');
        }

        return $query;
    }

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
                $idsPlaceholder = implode(',', $ids);

                $sql = "
                    UPDATE invoice_transactions it
                    SET brand_id = mhb.brand_id
                    FROM model_has_brands mhb
                    WHERE mhb.model_type = it.model_type
                      AND mhb.model_id = it.model_id
                      AND it.id IN ($idsPlaceholder)
                      AND it.brand_id IS NULL
                ";

                $updated = DB::affectingStatement($sql);
            } else {
                $updated = DB::table('invoice_transactions as it')
                    ->join('model_has_brands as mhb', function ($join) {
                        $join->on('mhb.model_type', '=', 'it.model_type')
                            ->on('mhb.model_id', '=', 'it.model_id');
                    })
                    ->whereIn('it.id', $ids)
                    ->whereNull('it.brand_id')
                    ->update([
                        'it.brand_id' => DB::raw('mhb.brand_id'),
                    ]);
            }

            $updatedTotal += (int) $updated;

            if ($onProgress) {
                $onProgress($updatedTotal, $candidateCount);
            }
        }

        return Command::SUCCESS;
    }

    public function asCommand(Command $command): int
    {
        $dryRun = (bool) $command->option('dry-run');
        $from = $command->option('from');
        $to = $command->option('to');
        $async = (bool) $command->option('async');

        $baseQuery = $this->baseQuery($from, $to);
        $candidateCount = (clone $baseQuery)->count();

        $command->line('Repair invoice_transactions.brand_id from model_has_brands');
        $command->line('Candidates: ' . number_format($candidateCount));

        if ($candidateCount === 0) {
            $command->info('Nothing to repair.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $sample = (clone $baseQuery)
                ->join('model_has_brands as mhb', function ($join) {
                    $join->on('mhb.model_type', '=', 'it.model_type')
                        ->on('mhb.model_id', '=', 'it.model_id');
                })
                ->select([
                    'it.id as invoice_transaction_id',
                    'it.model_type',
                    'it.model_id',
                    'mhb.brand_id as new_brand_id',
                    'it.date',
                ])
                ->orderBy('it.id')
                ->limit(10)
                ->get();

            $command->table(
                ['invoice_transaction_id', 'model_type', 'model_id', 'new_brand_id', 'date'],
                $sample->map(fn ($row) => [
                    $row->invoice_transaction_id,
                    $row->model_type,
                    $row->model_id,
                    $row->new_brand_id,
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
