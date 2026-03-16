<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Mar 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;

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

    public string $commandSignature = 'orders:fix_net_amounts {ids* : Order IDs to fix}';

    public function asCommand(Command $command): void
    {
        $orders = Order::whereIn('id', $command->argument('ids'))->get();

        $bar = $command->getOutput()->createProgressBar($orders->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($orders as $order) {
            $this->handle($order);
            $bar->advance();
        }

        $bar->finish();
        $command->newLine();
        $command->info("Fixed {$orders->count()} orders.");
    }
}
