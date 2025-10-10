<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 16:30:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBasket;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairCustomerBasketsCancelledOrders
{
    use WithActionUpdate;
    use WithFixedAddressActions;

    public function handle(Order $order): void
    {
        CustomerHydrateBasket::run($order->customer);
    }


    public string $commandSignature = 'repair:customer_baskets_cancelled_orders';

    public function asCommand(Command $command): void
    {
        $shopsIds = Shop::where('is_aiku', true)->pluck('id')->toArray();

        $count = Order::where('state', OrderStateEnum::CANCELLED)->whereIn('shop_id', $shopsIds)->count();


        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Order::where('state', OrderStateEnum::CANCELLED)->whereIn('shop_id', $shopsIds)->orderBy('date', 'desc')
            ->chunk(1000, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });
    }

}
