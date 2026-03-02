<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Dec 2025 15:05:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairOrderAmountsAfterMigration
{
    use WithActionUpdate;
    use WithFixedAddressActions;

    public function handle(Order $order): void
    {

        foreach ($order->transactions as $transaction) {
            if($transaction->model_type != 'Product'){
                continue;
            }



            $discountFactor=.9;




            /** @var \App\Models\Catalogue\Product $product */
            $product = $transaction->model;
            $currentHistoricAsset=$product->historicAsset;

            $gross = $currentHistoricAsset->price * $transaction->quantity_ordered;
            $net   = $gross * $discountFactor;

            $modelData = [
                'gross_amount'     => $gross,
                'net_amount'       => $net,
                'grp_net_amount' => $net * $transaction['grp_exchange'],
                'org_net_amount'   => $net * $transaction['org_exchange'],
            ];



            $transaction->update($modelData);
        }
        CalculateOrderTotalAmounts::run($order);
    }


    public string $commandSignature = 'orders:repair_order_amounts {order}';

    public function asCommand(Command $command): void
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();



        $this->handle($order);
    }

}
