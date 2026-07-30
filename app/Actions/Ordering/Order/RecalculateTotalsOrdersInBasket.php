<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 16:30:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Masters\MasterShop\RecalculateMasterShopMinorCurrencyPrices;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateCategoriesData;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterShop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class RecalculateTotalsOrdersInBasket implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(?int $orderID): string
    {
        return $orderID ?? 'empty';
    }

    public function handle(?int $orderID, ?Command $command = null, ?int $priceExchangeMasterShopID = null, ?string $priceExchangeCurrencyCode = null): void
    {
        try {
            $this->recalculate($orderID, $command);
        } finally {
            if ($priceExchangeMasterShopID && $priceExchangeCurrencyCode) {
                $this->updatePriceExchangeProgress($priceExchangeMasterShopID, $priceExchangeCurrencyCode);
            }
        }
    }

    protected function updatePriceExchangeProgress(int $masterShopID, string $currencyCode): void
    {
        $masterShop = MasterShop::find($masterShopID);
        if (!$masterShop) {
            return;
        }

        $basketsDone = (int)Cache::increment(RecalculateMasterShopMinorCurrencyPrices::basketsDoneKey($masterShop, $currencyCode));
        $progress    = RecalculateMasterShopMinorCurrencyPrices::getProgress($masterShop, $currencyCode);
        $remaining   = Cache::decrement(RecalculateMasterShopMinorCurrencyPrices::basketsRemainingKey($masterShop, $currencyCode));

        if ($remaining <= 0) {
            RecalculateMasterShopMinorCurrencyPrices::setProgress($masterShop, $currencyCode, array_merge($progress ?? [], [
                'state'        => 'finished',
                'baskets_done' => $basketsDone,
                'finished_at'  => now()->toIso8601String(),
            ]));
            RecalculateMasterShopMinorCurrencyPrices::forgetProgress($masterShop, $currencyCode);
        } elseif ($progress && $progress['state'] === 'repricing_baskets' && $basketsDone % 10 === 0) {
            RecalculateMasterShopMinorCurrencyPrices::setProgress($masterShop, $currencyCode, array_merge($progress, [
                'baskets_done' => $basketsDone,
            ]));
        }
    }

    protected function recalculate(?int $orderID, ?Command $command = null): void
    {
        if (!$orderID) {
            return;
        }
        $order = Order::find($orderID);
        if (!$order) {
            return;
        }

        /**
         * This reprices every transaction from the product's CURRENT price, which is only correct
         * while the order is still a basket. Callers select their orders when they queue the jobs,
         * but the jobs run later - a bulk run drains for hours - and an order the customer submits
         * in the meantime would otherwise be repriced after the fact, losing its agreed prices and
         * discounts and leaving the total out of step with what was already paid. Recheck at
         * execution time, not just at dispatch time.
         */
        if ($order->state !== OrderStateEnum::CREATING) {
            $command?->line("Order $order->reference is $order->state->value, no longer a basket, skipped");

            return;
        }

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
                    'historic_asset_id'       => $product->current_historic_asset_id,
                    'gross_amount'            => $netAmount,
                    'net_amount'              => $netAmount,
                    'current_discount_factor' => 1,
                    'family_id'               => $product->family_id,
                    'department_id'           => $product->department_id,
                    'sub_department_id'       => $product->sub_department_id,
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
        CalculateOrderTotalAmounts::run(
            order: $order,
            calculateShipping: true,
            calculateDiscounts: true,
            collectionChanged: false,
            forceRecalculate: true
        );

        $oldPayStatus = $order->pay_status;
        UpdateOrderPaymentsStatus::run($order, false);

        if ($command) {
            $order->refresh();

            if ($oldPayStatus != $order->pay_status) {
                $command->info(" >> Order: $order->slug  Payment status: $oldPayStatus?->value -> {$order->pay_status->value}");
            }

            $newTotal = $order->total_amount;
            if ($oldTotal != $newTotal) {
                $command->info("Order: $order->slug  {$order->shop->slug} - old total: $oldTotal - new total: $newTotal");
            }
        }
    }


    public string $commandSignature = 'orders:recalculate_totals_orders_in_basket {shop?} {--a|async} {--D|days=}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('shop')) {
            $shop     = Shop::where('slug', $command->argument('shop'))->firstOrFail();
            $shopsIds = [$shop->id];
        } else {
            $shopsIds = Shop::where('is_aiku', true)->pluck('id')->toArray();
        }

        $query = Order::where('state', OrderStateEnum::CREATING)->whereIn('shop_id', $shopsIds);

        if ($days = $command->option('days')) {
            $query->where('created_at', '>=', now()->subDays((int) $days));
        }

        $count = (clone $query)->count();


        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        $async = (bool)$command->option('async');

        $query->orderBy('date', 'desc')
            ->chunk(1000, function (Collection $models) use ($bar, $command, $async) {
                foreach ($models as $model) {
                    if ($async) {
                        RecalculateTotalsOrdersInBasket::dispatch($model->id)->onQueue('sales_slave');
                    } else {
                        $this->handle($model->id, $command);
                        $bar->advance();
                    }
                }
            });
    }

}
