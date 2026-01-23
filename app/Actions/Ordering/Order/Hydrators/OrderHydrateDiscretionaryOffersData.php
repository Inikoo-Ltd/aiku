<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 Jan 2026 14:03:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\Hydrators;

use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrderHydrateDiscretionaryOffersData
{
    use AsAction;

    public function handle(Order $order): void
    {
        $discretionaryOffersData = [];

        foreach (
            DB::table('transactions')->select('discretionary_offer', 'discretionary_offer_label', 'id')
                ->where('order_id', $order->id)->whereNotNull('discretionary_offer')
                ->whereNull('deleted_at')->get() as $transactionData
        ) {
            $discretionaryOffersData[$transactionData->id] = [
                'percentage' => $transactionData->discretionary_offer,
                'label'      => $transactionData->discretionary_offer_label ?? 'Discretionary Offer'
            ];
        }

        $order->update([
            'discretionary_offers_data' => $discretionaryOffersData,
        ]);
    }

    public function getCommandSignature(): string
    {
        return 'orders:hydrate_discretionary_offers_data {order_id?}';
    }

    public function asCommand($command): void
    {
        if ($orderID = $command->argument('order_id')) {
            /** @var \App\Models\Ordering\Order $order */
            $order = Order::findOrFail($orderID);
            $this->handle($order);

            return;
        }
        $aikuShops = Shop::where('is_aiku', true)->pluck('id')->toArray();

        $query = Order::whereIn('shop_id', $aikuShops);
        $count = $query->count();

        if ($count === 0) {
            $command->info('No orders found to hydrate.');

            return;
        }

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');

        $bar->start();

        $query->chunkById(1000, function ($orders) use ($bar) {
            foreach ($orders as $order) {
                $this->handle($order);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->newLine();
    }

}
