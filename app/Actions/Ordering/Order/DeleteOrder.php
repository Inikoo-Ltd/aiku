<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Helpers\Address\FixedAddressGarbageCollection;
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
            /**
             * The addresses an order points at are not always its own. A delivery address is
             * routinely the customer's, held by them as well, so deleting every address the
             * order references took the customer's with it - only the foreign key from
             * model_has_fixed_addresses stopped that. Collect the candidates, release this
             * order's hold on them, then let the garbage collection drop the ones nothing
             * else is still using.
             */
            $addressIds = $order->addresses->pluck('id')
                ->merge([$order->billing_address_id, $order->delivery_address_id])
                ->filter()
                ->unique();

            $order = DB::transaction(function () use ($order) {
                DB::table('model_has_fixed_addresses')->where('model_type', 'Order')->where('model_id', $order->id)->delete();
                DB::table('model_has_addresses')->where('model_type', 'Order')->where('model_id', $order->id)->delete();

                $order->transactions()->forceDelete();
                $order->forceDelete();

                return $order;
            });

            foreach ($addressIds as $addressId) {
                FixedAddressGarbageCollection::run($addressId);
            }
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
