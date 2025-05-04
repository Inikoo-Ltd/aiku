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
use Illuminate\Validation\ValidationException;

class DeleteOrder extends OrgAction
{
    use WithOrderingEditAuthorisation;
    use WithActionUpdate;

    public string $commandSignature = 'cancel:order {id}';

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order, array $deletedData = []): Order
    {
        if (in_array($order->state, [OrderStateEnum::CREATING, OrderStateEnum::SUBMITTED])) {
            $order->delete();

            $order = $this->update($order, $deletedData, ['data']);
            $order->transactions()->delete();

            return $order;
        }

        throw ValidationException::withMessages(['purchase_order' => 'You can not delete this order']);
    }

    /**
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
