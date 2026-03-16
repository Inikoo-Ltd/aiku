<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Mar 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class FixOrdersNetAmounts
{
    use WithActionUpdate;

    public function handle(Order $order): void
    {
        $order->update([
            'grp_net_amount' => $order->net_amount * $order->grp_exchange,
            'org_net_amount' => $order->net_amount * $order->org_exchange,
        ]);
    }

    public string $commandSignature = 'orders:fix_net_amounts {ids?* : Order IDs to fix (leave empty to fix all)}';

    public function asCommand(Command $command): void
    {
        $ids = $command->argument('ids');

        $query = $ids
            ? Order::whereIn('id', $ids)
            : Order::query();

        $query->whereNull('deleted_at');

        $total = $query->count();
        $bar   = $command->getOutput()->createProgressBar($total);
        $bar->setFormat('debug');
        $bar->start();

        $this->processInChunks($query, $bar, $command);

        $bar->finish();
        $command->newLine();
        $command->info("Fixed {$total} orders.");
    }

    private function processInChunks(Builder $query, $bar, Command $command): void
    {
        $query->with(['shop.currency', 'group.currency', 'organisation.currency'])
            ->chunkById(500, function ($orders) use ($bar, $command) {
                foreach ($orders as $order) {
                    $this->checkSuspiciousExchange($order, $command);
                    $this->handle($order);
                    $bar->advance();
                }
            });
    }

    private function checkSuspiciousExchange(Order $order, Command $command): void
    {
        $shopCurrency = $order->shop?->currency;
        $date         = $order->date;

        if (!$shopCurrency || !$date) {
            return;
        }

        $groupCurrency = $order->group?->currency;
        $orgCurrency   = $order->organisation?->currency;

        if ($groupCurrency && $order->grp_exchange) {
            $expectedGrp = GetHistoricCurrencyExchange::run($shopCurrency, $groupCurrency, $date);
            if ($expectedGrp && $this->isDifferenceOverThreshold($order->grp_exchange, $expectedGrp)) {
                $command->warn(
                    sprintf(
                        'Order #%s: grp_exchange suspicious — stored: %s, expected: %s (%.1f%%)',
                        $order->id,
                        $order->grp_exchange,
                        round($expectedGrp, 4),
                        $this->percentageDifference($order->grp_exchange, $expectedGrp)
                    )
                );
            }
        }

        if ($orgCurrency && $order->org_exchange) {
            $expectedOrg = GetHistoricCurrencyExchange::run($shopCurrency, $orgCurrency, $date);
            if ($expectedOrg && $this->isDifferenceOverThreshold($order->org_exchange, $expectedOrg)) {
                $command->warn(
                    sprintf(
                        'Order #%s: org_exchange suspicious — stored: %s, expected: %s (%.1f%%)',
                        $order->id,
                        $order->org_exchange,
                        round($expectedOrg, 4),
                        $this->percentageDifference($order->org_exchange, $expectedOrg)
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
