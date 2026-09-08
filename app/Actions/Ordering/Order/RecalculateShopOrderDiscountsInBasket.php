<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jun 2026 15:39:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Traits\WithRecalculateOrdersInBasket;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class RecalculateShopOrderDiscountsInBasket implements ShouldBeUnique
{
    use AsAction;
    use WithRecalculateOrdersInBasket;

    public function getJobUniqueId(int $shopId): string
    {
        return $shopId;
    }

    public function handle(int $shopId): void
    {
        $shop = Shop::find($shopId);
        if (!$shop) {
            return;
        }

        $this->recalculateOrdersInBasket($shop->orders()->where('state', OrderStateEnum::CREATING)->get());
    }

    public function getCommandSignature(): string
    {
        return 'recalculate-shop-order-discounts-in-basket {shop}';
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();
        $this->handle($shop->id);
    }

}
