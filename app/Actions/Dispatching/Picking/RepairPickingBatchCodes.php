<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 28 Apr 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\Picking;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairPickingBatchCodes
{
    use AsAction;

    private const int CHUNK_SIZE = 500;

    public string $commandSignature = 'dispatching:repair-picking-batch-codes
        {--dry-run : Show how many rows would be processed without writing}';

    public function handle(bool $dryRun = false, ?callable $onProgress = null): int
    {
        if ($dryRun) {
            return Command::SUCCESS;
        }

        DB::table('pickings')
            ->join('delivery_note_items', 'pickings.delivery_note_item_id', '=', 'delivery_note_items.id')
            ->whereNull('pickings.batch_code_id')
            ->whereNotNull('delivery_note_items.batch_code_id')
            ->select('pickings.id', 'delivery_note_items.batch_code_id')
            ->orderBy('pickings.id')
            ->chunk(self::CHUNK_SIZE, function ($rows) use ($onProgress) {
                foreach ($rows as $row) {
                    DB::table('pickings')
                        ->where('id', $row->id)
                        ->update(['batch_code_id' => $row->batch_code_id]);

                    if ($onProgress) {
                        $onProgress();
                    }
                }
            });

        return Command::SUCCESS;
    }

    public function asCommand(Command $command): int
    {
        $dryRun = (bool) $command->option('dry-run');

        $total = DB::table('pickings')
            ->join('delivery_note_items', 'pickings.delivery_note_item_id', '=', 'delivery_note_items.id')
            ->whereNull('pickings.batch_code_id')
            ->whereNotNull('delivery_note_items.batch_code_id')
            ->count();

        $command->line('Repair pickings.batch_code_id from delivery_note_items.batch_code_id');
        $command->line('Rows to repair: ' . number_format($total));

        if ($total === 0) {
            $command->info('Nothing to repair.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $command->info('Dry run only. No changes were written.');
            return Command::SUCCESS;
        }

        $bar = $command->getOutput()->createProgressBar($total);
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
