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

        $this->processInChunks($query, $bar);

        $bar->finish();
        $command->newLine();
        $command->info("Fixed {$total} orders.");
    }

    private function processInChunks(Builder $query, $bar): void
    {
        $query->chunkById(500, function ($orders) use ($bar) {
            foreach ($orders as $order) {
                $this->handle($order);
                $bar->advance();
            }
        });
    }
}
