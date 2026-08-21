<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Comms\Email\SendDispatchedReplacementOrderEmailToCustomer;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateDispatchTotals;
use App\Actions\Ordering\Order\UpdateState\DispatchOrderFromDeliveryNote;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class DispatchDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote, ?string $dispatchedAt = null, bool $repair = false): DeliveryNote
    {
        $oldState     = $deliveryNote->state;
        $dispatchedAt = $dispatchedAt ?? now();

        $deliveryNote = DB::transaction(function () use ($deliveryNote, $dispatchedAt, $repair) {
            data_set($modelData, 'dispatched_at', $dispatchedAt);
            data_set($modelData, 'state', DeliveryNoteStateEnum::DISPATCHED->value);

            foreach ($deliveryNote->deliveryNoteItems as $item) {
                $this->update($item, [
                    'state'               => DeliveryNoteItemStateEnum::DISPATCHED,
                    'dispatched_at'       => $dispatchedAt,
                    'quantity_dispatched' => $item->quantity_packed
                ]);
            }

            $deliveryNote = $this->update($deliveryNote, $modelData);

            $deliveryNote->refresh();
            if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
                foreach ($deliveryNote->orders as $order) {
                    if ($repair && $order->state == OrderStateEnum::DISPATCHED) {
                        continue;
                    }
                    DispatchOrderFromDeliveryNote::make()->action($order, $deliveryNote);
                }
            } elseif (!$repair) {
                SendDispatchedReplacementOrderEmailToCustomer::dispatch($deliveryNote);
            }

            return $deliveryNote;
        });

        $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
        $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::DISPATCHED);

        DeliveryNoteHydrateDispatchTotals::dispatch($deliveryNote);

        return $deliveryNote;
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote, ?string $dispatchedAt = null, bool $repair = false): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote, $dispatchedAt, $repair);
    }
}
