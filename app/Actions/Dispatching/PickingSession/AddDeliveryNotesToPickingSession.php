<?php

/*
* Author: Vika Aqordi
* Created on: 2026-04-27 16:15
* Github: https://github.com/aqordeon
* Copyright: 2026
*/

namespace App\Actions\Dispatching\PickingSession;

use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToInQueue;
use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItem;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickingSessions;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickingSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AddDeliveryNotesToPickingSession extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(PickingSession $pickingSession, array $deliveryNoteIds, bool $queued = false): PickingSession
    {
        return DB::transaction(function () use ($pickingSession, $deliveryNoteIds, $queued) {
            $user = request()->user();

            $validDeliveryNoteIds = DeliveryNote::whereIn('id', $deliveryNoteIds)
                ->get()
                ->filter(function ($deliveryNote) {
                    return $deliveryNote->pickingSessions->isEmpty()
                        && in_array($deliveryNote->state, [
                            DeliveryNoteStateEnum::UNASSIGNED,
                            DeliveryNoteStateEnum::QUEUED
                        ]);
                })
                ->pluck('id')
                ->toArray();

            if (empty($validDeliveryNoteIds)) {
                throw ValidationException::withMessages(
                    [
                        'message' => [
                            'delivery_notes' => __('Some delivery notes are already in a picking session or not in valid state.'),
                        ]
                    ]
                );
            }

            $pickingSession->deliveryNotes()->attach($validDeliveryNoteIds, [
                'organisation_id' => $pickingSession->organisation_id,
                'group_id'        => $pickingSession->group_id
            ]);

            $pickingSession->refresh();

            $deliveryNotes = DeliveryNote::whereIn('id', $validDeliveryNoteIds)->get();

            $numberItems         = 0;
            $numberDeliveryNotes = 0;
            foreach ($deliveryNotes as $deliveryNote) {

                if ($deliveryNote->state == DeliveryNoteStateEnum::CANCELLED) {
                    continue;
                }

                $numberDeliveryNotes++;
                if ($queued) {
                    UpdateDeliveryNoteStateToInQueue::make()->action($deliveryNote, $user);
                } else {
                    UpdateDeliveryNoteStateToInQueue::make()->action($deliveryNote, $user);
                }

                foreach ($deliveryNote->deliveryNoteItems as $item) {
                    $numberItems++;
                    UpdateDeliveryNoteItem::make()->action($item, [
                        'picking_session_id' => $pickingSession->id
                    ]);
                }
            }

            $pickingSession->updateQuietly([
                'number_items'          => $pickingSession->number_items + $numberItems,
                'number_delivery_notes' => $pickingSession->number_delivery_notes + $numberDeliveryNotes,
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
