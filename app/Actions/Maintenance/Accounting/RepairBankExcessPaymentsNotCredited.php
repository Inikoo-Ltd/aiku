<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Ordering\Order\AddBalanceFromExcessPaymentOrder;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairBankExcessPaymentsNotCredited
{
    use AsAction;

    public string $commandSignature = 'repair:bank_excess_not_credited {orders*} {--apply}';

    public function excessAmount(Order $order): float
    {
        $refundsTotal = Invoice::where('order_id', $order->id)->where('type', InvoiceTypeEnum::REFUND)->where('in_process', false)->sum('total_amount');

        return round($order->payment_amount - $order->total_amount - $refundsTotal, 2);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $orders = Order::whereIn('reference', $command->argument('orders'))->get();
        $missing = array_diff($command->argument('orders'), $orders->pluck('reference')->all());
        if ($missing) {
            $command->error('Orders not found: '.implode(', ', $missing));

            return 1;
        }

        foreach ($orders as $order) {
            $excess = $this->excessAmount($order);
            if ($excess <= 0) {
                $command->warn($order->reference.' has no excess ('.$excess.'), skipped');
                continue;
            }
            $command->line($order->reference.' '.$order->shop->code.' excess '.$excess.' '.$order->currency->code.' → customer '.$order->customer->reference);

            if ($command->option('apply')) {
                AddBalanceFromExcessPaymentOrder::make()->action($order);
                $command->info('  credited');
            }
        }

        if (!$command->option('apply')) {
            $command->info('Dry run, nothing written. Pass --apply to write.');
        }

        return 0;
    }
}
