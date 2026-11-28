<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Comms\Email\SendUnDispatchedOrderEmailToSubscribers;
use App\Actions\Dispatching\CrossDispatch\ReverseCrossDispatchCharge;
use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItem;
use App\Actions\Ordering\Order\UpdateState\RollbackDispatchedOrder;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\PartnerShipment;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UndispatchDeliveryNote extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote): void
    {
        $consolidation = PartnerShipment::whereNotNull('dispatched_at')
            ->whereNull('voided_at')
            ->whereHas('deliveryNotes', fn ($query) => $query->where('delivery_notes.id', $deliveryNote->id))
            ->value('reference');
        if ($consolidation) {
            throw ValidationException::withMessages(['message' => __('This delivery note left on consolidated delivery note :reference with its customs invoices, so it cannot be undispatched. Raise a ticket to sort it out.', ['reference' => $consolidation])]);
        }

        if ($deliveryNote->orders?->first()?->invoices()?->exists() && $deliveryNote->type == DeliveryNoteTypeEnum::ORDER) {
            throw ValidationException::withMessages(['message' => __('You need to delete invoice before undispatching a delivery note.')]);
        }

        UpdateDeliveryNote::make()->action($deliveryNote, [
            'state' => DeliveryNoteStateEnum::PACKED,
            'dispatched_at' => null,
            'finalised_at' => null
        ]);
        ReverseCrossDispatchCharge::run($deliveryNote);

        foreach ($deliveryNote->deliveryNoteItems as $item) {
            if ($item->state == DeliveryNoteItemStateEnum::DISPATCHED) {
                UpdateDeliveryNoteItem::make()->action($item, [
                    'state' => DeliveryNoteItemStateEnum::PACKED,
                    'dispatched_at' => null,
                    'finalised_at' => null
                ]);
            }
        }

        if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
            foreach ($deliveryNote->orders as $order) {
                RollbackDispatchedOrder::make()->action($order, true);
            }
        }

        SendUnDispatchedOrderEmailToSubscribers::dispatch($deliveryNote);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->route()?->getControllerClass() !== static::class) {
            return true;
        }

        $deliveryNote = $request->route('deliveryNote');

        return $deliveryNote instanceof DeliveryNote && $deliveryNote->canBeUndispatchedBy($request->user());
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): void
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        $this->handle($deliveryNote);
    }
}
