<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Mar 2026 11:42:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Ordering\Transaction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class FixTransactionsExchangedAmounts
{
    use WithActionUpdate;

    public function handle(Transaction $transaction): void
    {
        $transaction->update([
            'grp_net_amount' => $transaction->net_amount * $transaction->grp_exchange,
            'org_net_amount' => $transaction->net_amount * $transaction->org_exchange,
        ]);
    }

    public string $commandSignature = 'transactions:fix_net_amounts {ids?* : Transaction IDs to fix (leave empty to fix all)}';

    public function asCommand(Command $command): void
    {
        $ids = $command->argument('ids');

        $query = $ids
            ? Transaction::whereIn('id', $ids)
            : Transaction::query();

        $query->whereNull('deleted_at');

        $total = $query->count();
        $bar   = $command->getOutput()->createProgressBar($total);
        $bar->setFormat('debug');
        $bar->start();

        $this->processInChunks($query, $bar, $command);

        $bar->finish();
        $command->newLine();
        $command->info("Fixed {$total} transactions.");
    }

    private function processInChunks(Builder $query, $bar, Command $command): void
    {
        $query->with(['shop.currency', 'group.currency', 'organisation.currency'])
            ->chunkById(500, function ($transactions) use ($bar, $command) {
                foreach ($transactions as $transaction) {
                    $this->checkSuspiciousExchange($transaction, $command);
                    $this->handle($transaction);
                    $bar->advance();
                }
            });
    }

    private function checkSuspiciousExchange(Transaction $transaction, Command $command): void
    {
        $shopCurrency = $transaction->shop?->currency;
        $date         = $transaction->date;

        if (!$shopCurrency || !$date) {
            return;
        }

        $groupCurrency = $transaction->group?->currency;
        $orgCurrency   = $transaction->organisation?->currency;

        if ($groupCurrency && $transaction->grp_exchange) {
            $expectedGrp = GetHistoricCurrencyExchange::run($shopCurrency, $groupCurrency, $date);
            if ($expectedGrp && $this->isDifferenceOverThreshold($transaction->grp_exchange, $expectedGrp)) {
                $command->warn(
                    sprintf(
                        'Transaction #%s: grp_exchange suspicious — stored: %s, expected: %s (%.1f%%)',
                        $transaction->id,
                        $transaction->grp_exchange,
                        round($expectedGrp, 4),
                        $this->percentageDifference($transaction->grp_exchange, $expectedGrp)
                    )
                );
            }
        }

        if ($orgCurrency && $transaction->org_exchange) {
            $expectedOrg = GetHistoricCurrencyExchange::run($shopCurrency, $orgCurrency, $date);
            if ($expectedOrg && $this->isDifferenceOverThreshold($transaction->org_exchange, $expectedOrg)) {
                $command->warn(
                    sprintf(
                        'Transaction #%s: org_exchange suspicious — stored: %s, expected: %s (%.1f%%)',
                        $transaction->id,
                        $transaction->org_exchange,
                        round($expectedOrg, 4),
                        $this->percentageDifference($transaction->org_exchange, $expectedOrg)
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
