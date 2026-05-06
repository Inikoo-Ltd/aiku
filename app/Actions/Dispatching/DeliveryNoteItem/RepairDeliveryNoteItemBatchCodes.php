<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Models\Dispatching\BatchCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairDeliveryNoteItemBatchCodes
{
    use AsAction;

    private const int CHUNK_SIZE = 1000;

    public string $commandSignature = 'dispatching:repair-delivery-note-item-batch-codes
        {--dry-run : Show how many rows would be processed without writing}';

    public function handle(bool $dryRun = false, ?callable $onProgress = null): int
    {
        $total = DB::table('delivery_note_items')
            ->whereNotNull('batch_code')
            ->whereNull('batch_code_id')
            ->count();

        if ($total === 0 || $dryRun) {
            return Command::SUCCESS;
        }

        $processed = 0;

        $combinations = DB::table('delivery_note_items')
            ->select(['group_id', 'organisation_id', 'org_stock_id', 'batch_code', 'expiry_date'])
            ->whereNotNull('batch_code')
            ->whereNull('batch_code_id')
            ->distinct()
            ->get();

        foreach ($combinations as $row) {
            $batchCode = BatchCode::firstOrCreate(
                [
                    'organisation_id' => $row->organisation_id,
                    'org_stock_id'    => $row->org_stock_id,
                    'code'            => $row->batch_code,
                    'expiry_date'     => $row->expiry_date,
                ],
                [
                    'group_id' => $row->group_id,
                ]
            );

            DB::table('delivery_note_items')
                ->where('organisation_id', $row->organisation_id)
                ->where('org_stock_id', $row->org_stock_id)
                ->where('batch_code', $row->batch_code)
                ->where('expiry_date', $row->expiry_date)
                ->whereNull('batch_code_id')
                ->update(['batch_code_id' => $batchCode->id]);

            $processed++;

            if ($onProgress) {
                $onProgress($processed);
            }
        }

        return Command::SUCCESS;
    }

    public function asCommand(Command $command): int
    {
        $dryRun = (bool) $command->option('dry-run');

        $total = DB::table('delivery_note_items')
            ->whereNotNull('batch_code')
            ->whereNull('batch_code_id')
            ->count();

        $distinctCombinations = DB::table('delivery_note_items')
            ->select(['organisation_id', 'org_stock_id', 'batch_code', 'expiry_date'])
            ->whereNotNull('batch_code')
            ->whereNull('batch_code_id')
            ->distinct()
            ->count();

        $command->line('Repair batch_codes from delivery_note_items.batch_code');
        $command->line('Rows to repair   : ' . number_format($total));
        $command->line('Distinct batches : ' . number_format($distinctCombinations));

        if ($total === 0) {
            $command->info('Nothing to repair.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $command->info('Dry run only. No changes were written.');
            return Command::SUCCESS;
        }

        $bar = $command->getOutput()->createProgressBar($distinctCombinations);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        try {
            $this->handle(false, function () use ($bar) {
                $bar->advance();
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
