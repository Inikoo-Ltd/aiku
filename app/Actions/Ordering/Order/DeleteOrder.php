<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteOrder extends OrgAction
{
    use WithOrderingEditAuthorisation;
    use WithActionUpdate;
    use HasOrderHydrators;

    public string $commandSignature = 'order:delete {id}';

    /**
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Throwable
     */
    public function handle(Order $order): Order
    {
        if (in_array($order->state, [OrderStateEnum::CREATING, OrderStateEnum::SUBMITTED])) {
            $order = DB::transaction(function () use ($order) {
                DB::table('model_has_fixed_addresses')->where('model_type', 'Order')->where('model_id', $order->id)->delete();

                $order->transactions()->forceDelete();
                $order->forceDelete();

                $order->billingAddress()->forceDelete();
                $order->deliveryAddress()->forceDelete();

                foreach ($order->addresses as $address) {
                    $address->forceDelete();
                }



                return $order;
            });
            $this->orderHandlingHydrators($order, $order->state);

            return $order;
        }

        throw ValidationException::withMessages(['order' => 'You can not delete this order']);
    }


    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asCommand(Command $command): int
    {
        try {
            $order = Order::findOrFail($command->argument('id'));
        } catch (Exception) {
            $command->error('Order not found');

            return 1;
        }

        $this->handle($order);

        return 0;
    }
}
