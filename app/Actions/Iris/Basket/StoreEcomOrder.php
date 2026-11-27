<?php

/*
 * author Arya Permana - Kirin
 * created on 14-04-2025-14h-31m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Iris\Basket;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBasket;
use App\Actions\IrisAction;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreEcomOrder extends IrisAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer): Order
    {
        if ($customer->shop->type === ShopTypeEnum::DROPSHIPPING) {
            throw ValidationException::withMessages([
                'basket' => __('Dropshipping orders are placed from your sales channels, not from a basket.'),
            ]);
        }

        $order = StoreOrder::make()->action($customer, []);
        $customer->refresh();
        CustomerHydrateBasket::run($customer->id);

        return $order;
    }

    /**
     * @throws \Throwable
     */
    public function action(Customer $customer): Order
    {
        return $this->handle($customer);
    }


}
