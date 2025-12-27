<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 16:30:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

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

    public function handle(Order $order): void
    {
        OrderHydrateCategoriesData::run($order);
        CalculateOrderTotalAmounts::run($order, true, true, false, true);

        foreach($order->transactions as $transaction){

            $model = $transaction->model;
            if($model instanceof Product) {
                $transaction->update(
                    [
                        'family_id'         => $model->family_id,
                        'department_id'     => $model->department_id,
                        'sub_department_id' => $model->sub_department_id,
                    ]
                );
            }
        }


    }


    public string $commandSignature = 'orders:recalculate_totals_orders_in_basket';

    public function asCommand(Command $command): void
    {
        $shopsIds = Shop::where('is_aiku', true)->pluck('id')->toArray();

        $count = Order::where('state', OrderStateEnum::CREATING)->whereIn('shop_id', $shopsIds)->count();


        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Order::where('state', OrderStateEnum::CREATING)->whereIn('shop_id', $shopsIds)->orderBy('date', 'desc')
            ->chunk(1000, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });
    }

}
