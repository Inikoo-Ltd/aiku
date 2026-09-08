<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Maintenance\Accounting;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairInvoiceTransactionDates
{
    use AsAction;

    private const BACKUP_TABLE = 'invoice_transaction_date_repairs';

    private const DEFAULTED_TO_FETCH_TIME = <<<'SQL'
        invoices.date IS NOT NULL
        AND invoice_transactions.date::date <> invoices.date::date
        AND invoice_transactions.date::date = invoice_transactions.created_at::date
    SQL;

    public function handle(int $fromId, int $toId, ?int $organisationId = null, ?int $shopId = null): int
    {
        return DB::transaction(function () use ($fromId, $toId, $organisationId, $shopId) {
            $scope    = $this->scopeSql($organisationId, $shopId);
            $bindings = $this->scopeBindings($fromId, $toId, $organisationId, $shopId);

            DB::affectingStatement(
                'INSERT INTO '.self::BACKUP_TABLE.' (invoice_transaction_id, old_date, new_date, repaired_at)'
                .' SELECT invoice_transactions.id, invoice_transactions.date, invoices.date, now()'
                .' FROM invoice_transactions JOIN invoices ON invoices.id = invoice_transactions.invoice_id'
                .' WHERE invoice_transactions.id >= ? AND invoice_transactions.id < ?'
                .$scope
                .' AND '.self::DEFAULTED_TO_FETCH_TIME
                .' ON CONFLICT (invoice_transaction_id) DO NOTHING',
                $bindings
            );

            return DB::affectingStatement(
                'UPDATE invoice_transactions SET date = invoices.date FROM invoices'
                .' WHERE invoice_transactions.invoice_id = invoices.id'
                .' AND invoice_transactions.id >= ? AND invoice_transactions.id < ?'
                .$scope
                .' AND '.self::DEFAULTED_TO_FETCH_TIME,
                $bindings
            );
        });
    }

    public function countRepairable(?int $organisationId = null, ?int $shopId = null): int
    {
        return $this->repairableQuery($organisationId, $shopId)->count();
    }

    public function countRemainingMismatched(): int
    {
        return DB::table('invoice_transactions')
            ->join('invoices', 'invoice_transactions.invoice_id', '=', 'invoices.id')
            ->whereNotNull('invoices.date')
            ->whereRaw('invoice_transactions.date::date <> invoices.date::date')
            ->count();
    }

    private function repairableQuery(?int $organisationId, ?int $shopId): Builder
    {
        $query = DB::table('invoice_transactions')
            ->join('invoices', 'invoice_transactions.invoice_id', '=', 'invoices.id')
            ->whereRaw(self::DEFAULTED_TO_FETCH_TIME);

        if ($organisationId) {
            $query->where('invoice_transactions.organisation_id', $organisationId);
        }

        if ($shopId) {
            $query->where('invoice_transactions.shop_id', $shopId);
        }

        return $query;
    }

    private function scopeSql(?int $organisationId, ?int $shopId): string
    {
        return ($organisationId ? ' AND invoice_transactions.organisation_id = ?' : '')
            .($shopId ? ' AND invoice_transactions.shop_id = ?' : '');
    }

    private function scopeBindings(int $fromId, int $toId, ?int $organisationId, ?int $shopId): array
    {
        return array_values(array_filter(
            [$fromId, $toId, $organisationId, $shopId],
            fn ($binding) => $binding !== null
        ));
    }

    public string $commandSignature = 'repair:invoice_transaction_dates {--chunk=100000 : Ids scanned per statement} {--organisation= : Restrict to one organisation id} {--shop= : Restrict to one shop id} {--dry-run : Only report how many rows would be repaired}';

    public function asCommand(Command $command): int
    {
        $organisationId = $command->option('organisation') ? (int) $command->option('organisation') : null;
        $shopId         = $command->option('shop') ? (int) $command->option('shop') : null;

        $repairable = $this->countRepairable($organisationId, $shopId);
        $command->info('invoice transactions with date defaulted to fetch time: '.number_format($repairable));

        if ($command->option('dry-run') || $repairable === 0) {
            return 0;
        }

        $scoped = $this->repairableQuery($organisationId, $shopId);
        $minId  = (int) (clone $scoped)->min('invoice_transactions.id');
        $maxId  = (int) (clone $scoped)->max('invoice_transactions.id');
        $chunk  = max(1, (int) $command->option('chunk'));

        $bar = $command->getOutput()->createProgressBar((int) ceil(($maxId - $minId + 1) / $chunk));
        $bar->setFormat('debug');
        $bar->start();

        $repaired = 0;

        for ($fromId = $minId; $fromId <= $maxId; $fromId += $chunk) {
            $repaired += $this->handle($fromId, $fromId + $chunk, $organisationId, $shopId);
            $bar->advance();
        }

        $bar->finish();
        $command->newLine();

        $command->info('repaired invoice transactions: '.number_format($repaired));
        $command->info('old dates kept in '.self::BACKUP_TABLE.', restore with repair:invoice_transaction_dates_restore');
        $command->info('remaining with a date differing from their invoice: '.number_format($this->countRemainingMismatched()));
        $command->warn('time series are not rebuilt by this command, redo them to refresh the sales figures');

        return 0;
    }
}
