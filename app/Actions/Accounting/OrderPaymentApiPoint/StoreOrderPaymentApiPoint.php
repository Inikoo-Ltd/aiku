<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 18:11:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrderPaymentApiPoint;

use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

class StoreOrderPaymentApiPoint
{
    use AsObject;

    public function handle(Order $order): OrderPaymentApiPoint
    {
        data_set($modelData, 'group_id', $order->group_id);
        data_set($modelData, 'organisation_id', $order->organisation_id);
        data_set($modelData, 'ulid', Str::ulid());

        return $order->orderPaymentApiPoint()->create($modelData);

    }
}
