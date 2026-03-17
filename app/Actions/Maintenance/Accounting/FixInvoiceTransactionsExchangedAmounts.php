<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Mar 2026 11:42:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\InvoiceTransaction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class FixInvoiceTransactionsExchangedAmounts
{
    use WithActionUpdate;

    public function handle(InvoiceTransaction $invoiceTransaction): void
    {
        $invoiceTransaction->update([
            'grp_net_amount' => $invoiceTransaction->net_amount * $invoiceTransaction->grp_exchange,
            'org_net_amount' => $invoiceTransaction->net_amount * $invoiceTransaction->org_exchange,
        ]);
    }

    public string $commandSignature = 'invoice_transactions:fix_net_amounts {ids?* : Invoice Transaction IDs to fix (leave empty to fix all)}';

    public function asCommand(Command $command): void
    {
        $ids = $command->argument('ids');

        $query = $ids
            ? InvoiceTransaction::whereIn('id', $ids)
            : InvoiceTransaction::query();

        $query->whereNull('deleted_at');

        $total = $query->count();
        $bar   = $command->getOutput()->createProgressBar($total);
        $bar->setFormat('debug');
        $bar->start();

        $this->processInChunks($query, $bar, $command);

        $bar->finish();
        $command->newLine();
        $command->info("Fixed {$total} invoice transactions.");
    }

    private function processInChunks(Builder $query, $bar, Command $command): void
    {
        $query->with(['shop.currency', 'group.currency', 'organisation.currency'])
            ->chunkById(500, function ($invoiceTransactions) use ($bar, $command) {
                foreach ($invoiceTransactions as $invoiceTransaction) {
                    $this->checkSuspiciousExchange($invoiceTransaction, $command);
                    $this->handle($invoiceTransaction);
                    $bar->advance();
                }
            });
    }

    private function checkSuspiciousExchange(InvoiceTransaction $invoiceTransaction, Command $command): void
    {
        $shopCurrency = $invoiceTransaction->shop?->currency;
        $date         = $invoiceTransaction->date;

        if (!$shopCurrency || !$date) {
            return;
        }

        $groupCurrency = $invoiceTransaction->group?->currency;
        $orgCurrency   = $invoiceTransaction->organisation?->currency;

        if ($groupCurrency && $invoiceTransaction->grp_exchange) {
            $expectedGrp = GetHistoricCurrencyExchange::run($shopCurrency, $groupCurrency, $date);
            if ($expectedGrp && $this->isDifferenceOverThreshold($invoiceTransaction->grp_exchange, $expectedGrp)) {
                $command->warn(
                    sprintf(
                        'Invoice Transaction #%s: grp_exchange suspicious — stored: %s, expected: %s (%.1f%%)',
                        $invoiceTransaction->id,
                        $invoiceTransaction->grp_exchange,
                        round($expectedGrp, 4),
                        $this->percentageDifference($invoiceTransaction->grp_exchange, $expectedGrp)
                    )
                );
            }
        }

        if ($orgCurrency && $invoiceTransaction->org_exchange) {
            $expectedOrg = GetHistoricCurrencyExchange::run($shopCurrency, $orgCurrency, $date);
            if ($expectedOrg && $this->isDifferenceOverThreshold($invoiceTransaction->org_exchange, $expectedOrg)) {
                $command->warn(
                    sprintf(
                        'Invoice Transaction #%s: org_exchange suspicious — stored: %s, expected: %s (%.1f%%)',
                        $invoiceTransaction->id,
                        $invoiceTransaction->org_exchange,
                        round($expectedOrg, 4),
                        $this->percentageDifference($invoiceTransaction->org_exchange, $expectedOrg)
                    )
                );
            }
        }
    }

    private function isDifferenceOverThreshold(float|string $stored, float $expected, float $threshold = 5.0): bool
    {
        return $this->percentageDifference($stored, $expected) > $threshold;
    }

    private function percentageDifference(float|string $stored, float $expected): float
    {
        if ($expected == 0) {
            return 0;
        }

        return abs(((float) $stored - $expected) / $expected) * 100;
    }
}
