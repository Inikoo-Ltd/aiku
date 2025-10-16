<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Sept 2025 09:07:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\CRM;

use App\Actions\CRM\Customer\ForceDeleteCustomer;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBasket;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;

class RepairCustomersOrdersInBasketId
{
    use WithActionUpdate;


    protected function handle(Customer $customer, Command $command): void
    {
        if ($customer->shop->type != ShopTypeEnum::B2B) {
            return;
        }

        $order = Order::where('customer_id', $customer->id)->where('state', OrderStateEnum::CREATING)->first();

        $oldOrder = $customer->current_order_in_basket_id;
        if (!$order) {
            $customer->update([
                'current_order_in_basket_id' => $order?->id,
            ]);
        }
        if ($order && $oldOrder != $order->id) {
            CustomerHydrateBasket::run($customer);
            $command->info("Customer {$customer->slug}: $oldOrder  ->   {$order->id}");
        }
    }

    public string $commandSignature = 'customers:fix_orders_in_basket_id {customer?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('customer')) {
            $customer = Customer::find($command->argument('customer'));
            $this->handle($customer, $command);

            return;
        }


        Customer::withTrashed()
            ->orderBy('id')
            ->chunkById(500, function ($customers) use ($command) {
                foreach ($customers as $customer) {
                    $this->handle($customer, $command);
                }
            }, 'id');


        Customer::withTrashed()
            ->orderBy('id')
            ->chunkById(500, function ($customers) use ($command) {
                foreach ($customers as $customer) {
                    $this->handle($customer, $command);
                }
            }, 'id');
    }

}
