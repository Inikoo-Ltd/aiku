<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Mar 2026 10:12:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\OrgAction;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderGrGift extends OrgAction
{
    public function handle(Order $order, array $modelData): void
    {
        $orderData = $order->data;
        data_set($orderData, 'gr.selected_gift', $modelData['gift_id']);
        $order->update(['data' => $orderData]);
    }


    public function rules(): array
    {
        return [
            'gift_id' => ['nullable', 'numeric'],
        ];
    }

    public function asController(Order $order, ActionRequest $request): void
    {
        $this->initialisationFromShop($order->shop, $request);

        $this->handle($order, $this->validatedData);
    }
}
