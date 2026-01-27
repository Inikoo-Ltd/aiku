<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 27 Jan 2026 14:00:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Charge;

use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class StoreDiscretionaryChargeTransaction extends OrgAction
{
    public function handle(Order $order, array $modelData): Order
    {
        if (in_array($order->state, [
            OrderStateEnum::DISPATCHED,
            OrderStateEnum::FINALISED,
            OrderStateEnum::CANCELLED,
        ])) {
            abort(403);
        }


        $charge = $order->shop->charges()->where('type', ChargeTypeEnum::DISCRETIONARY->value)->first();
        if (!$charge) {
            abort(404);
        }


        StoreTransaction::run(
            $order,
            $charge->historicAsset,
            [
                'quantity_ordered' => 1,
                'gross_amount'     => $modelData['amount'],
                'net_amount'       => $modelData['amount'],
                'label'            => $modelData['label']
            ],
            false
        );

        $order->refresh();

        return $order;
    }

    public function rules(): array
    {
        return [
            'amount' => ['numeric', 'gt:0'],
            'label'  => ['string', 'max:255']
        ];
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }

}
