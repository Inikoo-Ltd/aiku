<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Jul 2025 09:59:27 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Helpers\TaxCategory\GetTaxCategory;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ResetOrderTaxCategory
{

    use asAction;


    public function handle(Order $order,Command $command): Order
    {
        $customer = $order->customer;
        if ($customer) {
            $taxNumber = $customer->taxNumber;

            $command->info("Resetting tax category for order $order->slug");
            $command->info("New tax number $taxNumber->number");

            $tacCategory = GetTaxCategory::run(
                country: $order->organisation->country,
                taxNumber: $taxNumber,
                billingAddress: $order->billingAddress,
                deliveryAddress: $order->deliveryAddress
            );
            $command->info("New tax category rate $tacCategory->rate");


            UpdateOrder::make()->action($order, [
                'tax_category_id' => $tacCategory->id,
            ]);

            CalculateOrderTotalAmounts::run($order);

        }


        return $order;
    }

    public function getCommandSignature(): string
    {
        return 'orders:reset_tax_category {order}';
    }

    public function asCommand(Command $command): int
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();
        $this->handle($order,$command);

        return 0;
    }

}
