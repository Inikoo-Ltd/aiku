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
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Ordering\Order;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class SendOrderBackToBasket extends OrgAction
{

    use WithOrderingEditAuthorisation;
    use WithActionUpdate;
    use HasOrderHydrators;

    private Order $order;

    public function handle(Order $order): Order
    {
        $order->transactions()->update([
            'state'  => TransactionStateEnum::CREATING,
            'status' => TransactionStatusEnum::CREATING
        ]);

        $this->update($order, [
            'state'  => OrderStateEnum::CREATING,
            'status' => OrderStatusEnum::CREATING
        ]);
        $this->orderHydrators($order);

        return $order;
    }


    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if ($this->order->state != OrderStateEnum::SUBMITTED) {
            $validator->errors()->add('state', 'You only can return to basket if current status is submitted');
        }
    }

    public function action(Order $order): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order);
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }
}
