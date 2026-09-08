<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Maintenance\Accounting;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RestoreInvoiceTransactionDates
{
    use AsAction;

    private const BACKUP_TABLE = 'invoice_transaction_date_repairs';

    public function handle(int $fromId, int $toId): int
    {
        return DB::affectingStatement(
            'UPDATE invoice_transactions SET date = backup.old_date FROM '.self::BACKUP_TABLE.' as backup'
            .' WHERE backup.invoice_transaction_id = invoice_transactions.id'
            .' AND invoice_transactions.id >= ? AND invoice_transactions.id < ?'
            .' AND invoice_transactions.date = backup.new_date',
            [$fromId, $toId]
        );
    }

    public string $commandSignature = 'repair:invoice_transaction_dates_restore {--chunk=100000 : Ids scanned per statement} {--dry-run : Only report how many rows would be restored}';

    public function asCommand(Command $command): int
    {
        $restorable = DB::table(self::BACKUP_TABLE)->count();
        $command->info('invoice transactions with a backed up date: '.number_format($restorable));

        if ($command->option('dry-run') || $restorable === 0) {
            return 0;
        }

        $minId = (int) DB::table(self::BACKUP_TABLE)->min('invoice_transaction_id');
        $maxId = (int) DB::table(self::BACKUP_TABLE)->max('invoice_transaction_id');
        $chunk = max(1, (int) $command->option('chunk'));

        $bar = $command->getOutput()->createProgressBar((int) ceil(($maxId - $minId + 1) / $chunk));
        $bar->setFormat('debug');
        $bar->start();

        $restored = 0;

        for ($fromId = $minId; $fromId <= $maxId; $fromId += $chunk) {
            $restored += $this->handle($fromId, $fromId + $chunk);
            $bar->advance();
        }

        $bar->finish();
        $command->newLine();
        $command->info('restored invoice transactions: '.number_format($restored));

        return 0;
    }
}
