<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Dec 2025 22:59:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\Hydrators;

use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrderHydrateCategoriesData
{
    use AsAction;

    public function handle(Order $order): void
    {
        $familyIds = [];
        $categoriesData = [
            'family'         => [],
            'sub_department' => [],
            'department'     => [],
        ];

        $transactions = DB::table('transactions')
            ->join('assets', 'transactions.asset_id', '=', 'assets.id')
            ->join('products', 'assets.id', '=', 'products.asset_id')
            ->where('transactions.order_id', $order->id)
            ->where('transactions.model_type', 'Product')
            ->where('transactions.deleted_at', null)
            ->select([
                'products.family_id',
                'products.sub_department_id',
                'products.department_id',
                DB::raw('SUM(transactions.quantity_ordered) as total_quantity'),
                DB::raw('SUM(transactions.net_amount) as total_net_amount'),
            ])
            ->groupBy([
                'products.family_id',
                'products.sub_department_id',
                'products.department_id',
            ])
            ->get();

        foreach ($transactions as $transaction) {
            if ($transaction->family_id) {
                $categoriesData['family'][$transaction->family_id] = [
                    'quantity'   => (float) ($categoriesData['family'][$transaction->family_id]['quantity'] ?? 0) + $transaction->total_quantity,
                    'net_amount' => (float) ($categoriesData['family'][$transaction->family_id]['net_amount'] ?? 0) + $transaction->total_net_amount,
                ];
                $familyIds[] = $transaction->family_id;
            }
            if ($transaction->sub_department_id) {
                $categoriesData['sub_department'][$transaction->sub_department_id] = [
                    'quantity'   => (float) ($categoriesData['sub_department'][$transaction->sub_department_id]['quantity'] ?? 0) + $transaction->total_quantity,
                    'net_amount' => (float) ($categoriesData['sub_department'][$transaction->sub_department_id]['net_amount'] ?? 0) + $transaction->total_net_amount,
                ];
            }
            if ($transaction->department_id) {
                $categoriesData['department'][$transaction->department_id] = [
                    'quantity'   => (float) ($categoriesData['department'][$transaction->department_id]['quantity'] ?? 0) + $transaction->total_quantity,
                    'net_amount' => (float) ($categoriesData['department'][$transaction->department_id]['net_amount'] ?? 0) + $transaction->total_net_amount,
                ];
            }
        }

        $categoriesData['family_ids'] = $familyIds;


        $order->update([
            'categories_data' => $categoriesData,
        ]);
    }

    public function getCommandSignature(): string
    {
        return 'orders:hydrate_categories_data {order_id?}';
    }

    public function asCommand($command): void
    {

        if ($orderID = $command->argument('order_id')) {
            /** @var \App\Models\Ordering\Order $order */
            $order = Order::findOrFail($orderID);
            $this->handle($order);

            return;
        }
        $aikuShops=Shop::where('is_aiku',true)->pluck('id')->toArray();

        $query = Order::whereIn('shop_id',$aikuShops);
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
