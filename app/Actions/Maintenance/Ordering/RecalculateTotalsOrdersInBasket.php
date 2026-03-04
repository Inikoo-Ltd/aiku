<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 16:30:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateCategoriesData;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RecalculateTotalsOrdersInBasket
{
    use WithActionUpdate;
    use WithFixedAddressActions;

    public function handle(Order $order, Command|null $command = null): void
    {

        $oldTotal = $order->total_amount;
        foreach ($order->transactions as $transaction) {
            if ($transaction->model instanceof Product) {
                /** @var Product $product */
                $product = $transaction->model;

                $oldHistoric = $transaction->historic_asset_id;
                $netAmount   = $product->currentHistoricProduct->price * $transaction->quantity_ordered;
                if (!is_numeric($netAmount)) {
                    $netAmount = 0;
                }
                $shop        = $transaction->shop;
                $orgExchange = GetCurrencyExchange::run($shop->currency, $shop->organisation->currency);
                $grpExchange = GetCurrencyExchange::run($shop->currency, $shop->organisation->group->currency);

                $transactionData = [
                    'historic_asset_id' => $product->current_historic_asset_id,
                    'gross_amount'      => $netAmount,
                    'net_amount'        => $netAmount,
                    'family_id'         => $product->family_id,
                    'department_id'     => $product->department_id,
                    'sub_department_id' => $product->sub_department_id,
                ];

                data_set($transactionData, 'org_exchange', $orgExchange);
                data_set($transactionData, 'org_net_amount', $orgExchange * $netAmount);

                data_set($transactionData, 'grp_exchange', $grpExchange);
                data_set($transactionData, 'grp_net_amount', $grpExchange * $netAmount);

                $transaction->update($transactionData);

                if ($command && $oldHistoric != $product->current_historic_asset_id) {
                    $command->info(" >> Product: $product->slug - old historic asset id: $oldHistoric - new historic asset id: $product->current_historic_asset_id");
                }
            }
        }

        OrderHydrateCategoriesData::run($order);
        CalculateOrderTotalAmounts::run($order, true, true, false, true);

        $order->refresh();
        $newTotal = $order->total_amount;
        if ($command && $oldTotal != $newTotal) {
            $command->info("Order: $order->slug - old total: $oldTotal - new total: $newTotal");
        }

    }


    public string $commandSignature = 'orders:recalculate_totals_orders_in_basket {shop?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('shop')) {
            $shop     = Shop::where('slug', $command->argument('shop'))->firstOrFail();
            $shopsIds = [$shop->id];
        } else {
            $shopsIds = Shop::where('is_aiku', true)->pluck('id')->toArray();
        }


        $count = Order::where('state', OrderStateEnum::CREATING)->whereIn('shop_id', $shopsIds)->count();


        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Order::where('state', OrderStateEnum::CREATING)->whereIn('shop_id', $shopsIds)->orderBy('date', 'desc')
            ->chunk(1000, function (Collection $models) use ($bar, $command) {
                foreach ($models as $model) {
                    $this->handle($model, $command);
                    $bar->advance();
                }
            });
    }

}
