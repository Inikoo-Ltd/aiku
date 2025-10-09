<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:38:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrderPaymentApiPoint\WebHooks;

use App\Actions\Retina\Dropshipping\Orders\WithRetinaOrderPlacedRedirection;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class RedirectSuccessPaymentOrder extends RetinaAction
{
    use WithRetinaOrderPlacedRedirection;


    public function handle(Order $order): array
    {
        return [
            'status'   => 'success',
            'success'  => true,
            'reason'   => 'Order paid successfully',
            'order'    => $order,
            'order_id' => $order->id,

        ];
    }



    public function asController(Order $order, ActionRequest $request): array
    {

        $this->initialisation($request);

        return $this->handle($order);
    }

}
