<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Dec 2025 15:05:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairAuroraOrdersExchangeTotals
{
    use WithActionUpdate;
    use WithFixedAddressActions;

    public function handle(Order $order): void
    {
        $shop = $order->shop;

        $orgExchange = GetHistoricCurrencyExchange::run($shop->currency, $shop->organisation->currency, $order->date);
        $grpExchange = GetHistoricCurrencyExchange::run($shop->currency, $shop->group->currency, $order->date);


        $grpNetAmount = $order->net_amount * $grpExchange;
        $orgNetAmount = $order->net_amount * $orgExchange;

        $order->update([
            'org_exchange'   => $orgExchange,
            'grp_exchange'   => $grpExchange,
            'org_net_amount' => $orgNetAmount,
            'grp_net_amount' => $grpNetAmount,
        ]);
    }


    public string $commandSignature = 'orders:repair_address_exchange_totals {order?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('order')) {
            $order = Order::find($command->argument('order'));
            $this->handle($order);

            return;
        }

        $bar = $command->getOutput()->createProgressBar(DB::table('orders')->select('id')->whereNotNull('source_id')->count());
        $bar->setFormat('debug');
        $bar->start();

        $i = 0;
        DB::table('orders')->select('id')->whereNotNull('source_id')->chunkById(1000, function ($orders) use ($command, $bar, &$i) {
            foreach ($orders as $orderID) {
                $i++;
                $order = Order::find($orderID->id);
                if ($order) {
                    ///  print "$i $order->id \n";
                    $this->handle($order);
                    $bar->advance();
                }
            }
        });
    }

}
