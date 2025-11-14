<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 02 Oct 2025 16:13:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsObject;

class CalculateOrderDiscounts
{
    use AsObject;

    protected bool $toBeConfirmed = false;

    public function handle(Order $order): Order
    {
        return $order;
    }


}
