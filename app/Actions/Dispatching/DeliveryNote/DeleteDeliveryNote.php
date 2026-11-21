<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\PickedBay\DetachDeliveryNoteFromPickedBays;
use App\Actions\Dispatching\PickingSession\AutoFinishPackingPickingSession;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\PartnerShipment;
use App\Models\Dispatching\Trolley;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        /*
         * INI-484. Deleting removes the picks without putting the goods back on the shelf. On a note worked in another
         * company's warehouse that would leave that company's stock short for an order it does not own, so it is cancelled instead.
         */
        if ($deliveryNote->isCrossDispatch() && $deliveryNote->pickings()->exists()) {
            throw ValidationException::withMessages([
                'delivery_note' => __('This delivery note was picked in another company\'s warehouse and cannot be deleted. Cancel it, so the picked goods go back to the shelf.'),
            ]);
        }

        $pickingSessions = $deliveryNote->pickingSessions()->get();

        $deliveryNote = DB::transaction(function () use ($deliveryNote, $modelData) {
            $deliveryNote = $this->update($deliveryNote, $modelData);

            DB::table('delivery_note_has_trolleys')->where('delivery_note_id', $deliveryNote->id)->delete();
            DetachDeliveryNoteFromPickedBays::run($deliveryNote);
            DB::table('picking_session_has_delivery_notes')->where('delivery_note_id', $deliveryNote->id)->delete();
            PartnerShipment::releaseDeliveryNote($deliveryNote->id);


            Trolley::where('current_delivery_note_id', $deliveryNote->id)->update(['current_delivery_note_id' => null]);

            foreach ($deliveryNote->deliveryNoteItems as $item) {
                $item->pickings()->delete();
            }
            $deliveryNote->deliveryNoteItems()->delete();
            $deliveryNote->delete();

            return $deliveryNote;
        });

        foreach ($pickingSessions as $pickingSession) {
            AutoFinishPackingPickingSession::run($pickingSession);
        }

        $this->storeDeliveryNoteHydrators($deliveryNote);
        $this->deliveryNoteHandlingHydrators($deliveryNote, $deliveryNote->state);

        return $deliveryNote;
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $modelData);

        return $this->handle($deliveryNote, $this->validatedData);
    }

    public function getCommandSignature(): string
    {
        return 'delete:delivery_note {delivery_note}';
    }


    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): string
    {
        $deliveryNote = DeliveryNote::where('slug', $command->argument('delivery_note'))->firstOrFail();
        $this->handle($deliveryNote, []);

        return 'Delivery note deleted';
    }

}
