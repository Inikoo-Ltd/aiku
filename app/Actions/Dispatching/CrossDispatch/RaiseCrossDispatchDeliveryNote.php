<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\CrossDispatch;

use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Enums\Dispatching\CrossDispatch\CrossDispatchRefusalEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class RaiseCrossDispatchDeliveryNote
{
    use AsObject;

    /**
     * Raises the delivery note of a submitted order in another company's warehouse: the whole order or nothing.
     * Reached from SendOrderToWarehouse's routing (INI-485) and from staff through SendOrderToOtherWarehouse, which
     * checks the shop's list and the office holds first. An order that already has a live note at home is refused here;
     * taking it over from the home warehouse is a later step.
     *
     * @throws \Illuminate\Validation\ValidationException with the resolver's refusals
     * @throws \Throwable
     */
    public function handle(Order $order, Organisation $dispatcher): DeliveryNote
    {
        return DB::transaction(function () use ($order, $dispatcher) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            $refusals = ResolveCrossDispatch::run($order, $dispatcher)['refusals'];

            if (
                $order->state !== OrderStateEnum::SUBMITTED
                || $order->deliveryNotes()->where('delivery_notes.state', '!=', DeliveryNoteStateEnum::CANCELLED)->exists()
            ) {
                $refusals[] = ['reason' => CrossDispatchRefusalEnum::ORDER_STATE->value];
            }

            if (DeliveryNote::withTrashed()->where('organisation_id', $dispatcher->id)->whereRaw('lower(reference) = lower(?)', [$order->reference])->exists()) {
                $refusals[] = ['reason' => CrossDispatchRefusalEnum::REFERENCE_TAKEN->value];
            }

            if ($refusals) {
                throw ResolveCrossDispatch::refusal($refusals);
            }

            $deliveryNote = SendOrderToWarehouse::make()->action($order, [], dispatcher: $dispatcher);

            if (!$deliveryNote?->isCrossDispatch()) {
                throw ResolveCrossDispatch::refusal([['reason' => CrossDispatchRefusalEnum::ORDER_HELD->value]]);
            }

            return $deliveryNote;
        });
    }
}
