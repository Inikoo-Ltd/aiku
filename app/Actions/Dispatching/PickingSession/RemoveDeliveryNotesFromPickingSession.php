<?php
/*
* Author: Vika Aqordi
* Created on: 2026-04-28 08:37
* Github: https://github.com/aqordeon
* Copyright: 2026
*/

namespace App\Actions\Dispatching\PickingSession;

use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItem;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickingSessions;
use App\Actions\OrgAction;
use App\Models\Inventory\PickingSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class RemoveDeliveryNotesFromPickingSession extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(PickingSession $pickingSession, array $deliveryNoteIds): PickingSession
    {
        return DB::transaction(function () use ($pickingSession, $deliveryNoteIds) {
            $existingDeliveryNotes = $pickingSession->deliveryNotes()->whereIn('delivery_notes.id', $deliveryNoteIds)->get();

            if ($existingDeliveryNotes->isEmpty()) {
                throw ValidationException::withMessages(
                    [
                        'message' => [
                            'delivery_notes' => 'No delivery notes found in this picking session.',
                        ]
                    ]
                );
            }

            $numberItems = 0;
            $numberDeliveryNotes = $existingDeliveryNotes->count();

            foreach ($existingDeliveryNotes as $deliveryNote) {
                foreach ($deliveryNote->deliveryNoteItems as $item) {
                    if ($item->picking_session_id === $pickingSession->id) {
                        $numberItems++;
                        UpdateDeliveryNoteItem::make()->action($item, [
                            'picking_session_id' => null
                        ]);
                    }
                }
            }

            $pickingSession->deliveryNotes()->detach($deliveryNoteIds);

            $pickingSession->updateQuietly([
                'number_items'          => max(0, $pickingSession->number_items - $numberItems),
                'number_delivery_notes' => max(0, $pickingSession->number_delivery_notes - $numberDeliveryNotes),
            ]);

            WarehouseHydratePickingSessions::dispatch($pickingSession->warehouse);

            return $pickingSession;
        });
    }

    public function rules(): array
    {
        return [
            'delivery_notes' => ['required', 'array'],
        ];
    }

    public function asController(PickingSession $pickingSession, ActionRequest $request): PickingSession
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        return $this->handle($pickingSession, $this->validatedData['delivery_notes']);
    }

    public function htmlResponse(PickingSession $pickingSession, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.dispatching.picking_sessions.show', [
            'organisation'   => $pickingSession->organisation->slug,
            'warehouse'     => $pickingSession->warehouse->slug,
            'pickingSession' => $pickingSession->slug,
        ]);
    }
}
