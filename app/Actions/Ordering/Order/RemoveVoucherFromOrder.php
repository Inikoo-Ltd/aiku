<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Jun 2026 10:27:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Models\Ordering\Order;
use Illuminate\Http\Request;

class RemoveVoucherFromOrder extends OrgAction
{
    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order): void
    {
        $order->update(
            [
                'offer_voucher_id' => null
            ]
        );

        CalculateOrderDiscounts::run($order);



    }


    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Order $order, Request $request): void
    {
        $this->handle($order);
    }
}
