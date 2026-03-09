<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Mar 2026 01:27:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Ordering\Order\RecalculateTotalsOrdersInBasket;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateOrdersInBasketsAfterProductUpdated implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int|null $productID): string
    {
        return $productID ?? 'empty';
    }

    public function handle(int|null $productID, Command|null $command = null): void
    {
        if (!$productID) {
            return;
        }
        $product = Product::find($productID);
        if (!$product) {
            return;
        }

        if (!$product->shop->is_aiku) {
            return;
        }


        foreach (DB::table('transactions')
            ->leftJoin('orders', 'orders.id', '=', 'transactions.order_id')
            ->where('orders.state', OrderStateEnum::CREATING)
            ->where('model_type', 'Product')
            ->where('model_id', $product->id)->get() as $transactionData) {


            if ($command) {
                $order = Order::find($transactionData->order_id);
                $command->getOutput()->writeln("Updating order $order->slug ".$order->state->value);
            }

            RecalculateTotalsOrdersInBasket::dispatch($transactionData->order_id)->delay(30);

        }





    }

    public string $commandSignature = 'product:recalculate_totals_orders_in_basket {product}';

    public function asCommand(Command $command): void
    {
        $product = Product::where('slug', $command->argument('product'))->firstOrFail();
        $this->handle($product->id, $command);
    }


}
