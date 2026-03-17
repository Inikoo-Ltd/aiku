<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Mar 2026 11:42:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class FixInvoicesExchangedAmounts
{
    use WithActionUpdate;

    public function handle(Invoice $invoice): void
    {
        $invoice->update([
            'grp_net_amount' => $invoice->net_amount * $invoice->grp_exchange,
            'org_net_amount' => $invoice->net_amount * $invoice->org_exchange,
        ]);
    }

    public string $commandSignature = 'invoices:fix_net_amounts {ids?* : Invoice IDs to fix (leave empty to fix all)}';

    public function asCommand(Command $command): void
    {
        $ids = $command->argument('ids');

        $query = $ids
            ? Invoice::whereIn('id', $ids)
            : Invoice::query();

        $query->whereNull('deleted_at');

        $total = $query->count();
        $bar   = $command->getOutput()->createProgressBar($total);
        $bar->setFormat('debug');
        $bar->start();

        $this->processInChunks($query, $bar, $command);

        $bar->finish();
        $command->newLine();
        $command->info("Fixed {$total} invoices.");
    }

    private function processInChunks(Builder $query, $bar, Command $command): void
    {
        $query->with(['shop.currency', 'group.currency', 'organisation.currency'])
            ->chunkById(500, function ($invoices) use ($bar, $command) {
                foreach ($invoices as $invoice) {
                    $this->checkSuspiciousExchange($invoice, $command);
                    $this->handle($invoice);
                    $bar->advance();
                }
            });
    }

    private function checkSuspiciousExchange(Invoice $invoice, Command $command): void
    {
        $shopCurrency = $invoice->shop?->currency;
        $date         = $invoice->date;

        if (!$shopCurrency || !$date) {
            return;
        }

        $groupCurrency = $invoice->group?->currency;
        $orgCurrency   = $invoice->organisation?->currency;

        if ($groupCurrency && $invoice->grp_exchange) {
            $expectedGrp = GetHistoricCurrencyExchange::run($shopCurrency, $groupCurrency, $date);
            if ($expectedGrp && $this->isDifferenceOverThreshold($invoice->grp_exchange, $expectedGrp)) {
                $command->warn(
                    sprintf(
                        'Invoice #%s: grp_exchange suspicious — stored: %s, expected: %s (%.1f%%)',
                        $invoice->id,
                        $invoice->grp_exchange,
                        round($expectedGrp, 4),
                        $this->percentageDifference($invoice->grp_exchange, $expectedGrp)
                    )
                );
            }
        }

        if ($orgCurrency && $invoice->org_exchange) {
            $expectedOrg = GetHistoricCurrencyExchange::run($shopCurrency, $orgCurrency, $date);
            if ($expectedOrg && $this->isDifferenceOverThreshold($invoice->org_exchange, $expectedOrg)) {
                $command->warn(
                    sprintf(
                        'Invoice #%s: org_exchange suspicious — stored: %s, expected: %s (%.1f%%)',
                        $invoice->id,
                        $invoice->org_exchange,
                        round($expectedOrg, 4),
                        $this->percentageDifference($invoice->org_exchange, $expectedOrg)
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
