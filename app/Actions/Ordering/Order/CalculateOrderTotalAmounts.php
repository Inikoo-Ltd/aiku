<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Jul 2024 00:03:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\WithOrganisationsArgument;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;

class CalculateOrderTotalAmounts extends OrgAction
{
    use WithOrganisationsArgument;

    public function handle(Order $order): void
    {
        $items      = $order->transactions()->get();
        $itemsNet   = $items->sum('net_amount');
        $itemsGross = $items->sum('gross_amount');
        $tax        = $order->taxCategory->rate;


        $shippingAmount = $order->transactions()->where('model_type', 'ShippingZone')->sum('net_amount');
        $chargesAmount  = $order->transactions()->where('model_type', 'Charge')->sum('net_amount');


        $taxAmount   = $itemsNet * $tax;
        $totalAmount = $itemsNet + $taxAmount;
        $grpNet      = $itemsNet * $order->grp_exchange;
        $orgNet      = $itemsNet * $order->org_exchange;

        data_set($modelData, 'net_amount', $itemsNet);
        data_set($modelData, 'total_amount', $totalAmount);
        data_set($modelData, 'tax_amount', $taxAmount);
        data_set($modelData, 'goods_amount', $itemsNet);
        data_set($modelData, 'grp_net_amount', $grpNet);
        data_set($modelData, 'org_net_amount', $orgNet);
        data_set($modelData, 'gross_amount', $itemsGross);
        data_set($modelData, 'shipping_amount', $shippingAmount);
        data_set($modelData, 'charges_amount', $chargesAmount);

        $order->update($modelData);



    }

    public string $commandSignature = 'order:totals {--s|slugs=}';

    public function asCommand(Command $command): int
    {
        $exitCode = 0;
        if (!$command->option('slugs')) {
            if ($command->argument('organisations')) {
                $this->organisation = $this->getOrganisations($command)->first();
            }

            $this->loopAll($command);
        } else {
            $slug  = $command->option('slugs');
            $order = Order::where('slug', $slug)->first();
            if ($order) {
                $this->handle($order);
                $command->line("Order $order->reference hydrated 💦");
            } else {
                $command->error("Model not found");
                $exitCode = 1;
            }
        }

        return $exitCode;
    }

    protected function loopAll(Command $command): void
    {
        $command->withProgressBar(Order::all(), function ($model) {
            if ($model) {
                $this->handle($model);
            }
        });
        $command->info("");
    }

}
