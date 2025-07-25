<?php

/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\PickingSession;

use App\Actions\Dispatching\DeliveryNote\StartHandlingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteStateToInQueue;
use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItem;
use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\OrgAction;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StorePickingSession extends OrgAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Throwable
     */
    public function handle(Warehouse $warehouse, array $modelData, bool $queued = false): PickingSession
    {
        $pickingSession = DB::transaction(function () use ($warehouse, $modelData, $queued) {
            $deliveryNoteIds = Arr::pull($modelData, 'delivery_notes');
            $validDeliveryNoteIds = DeliveryNote::whereIn('id', $deliveryNoteIds)
                ->get()
                ->filter(function ($deliveryNote) {
                    return $deliveryNote->pickingSessions->isEmpty();
                })
                ->pluck('id')
                ->toArray();

            if (empty($validDeliveryNoteIds)) {
                throw ValidationException::withMessages(
                     [
                        'message' => [
                            'delivery_notes' => 'All selected delivery notes are already in a picking session.',
                        ]
                    ]
                );
            }

            data_set(
                $modelData,
                'reference',
                GetSerialReference::run(
                    container: $warehouse->organisation,
                    modelType: SerialReferenceModelEnum::PICKING_SESSION,
                )
            );

            data_set($modelData, 'state', PickingSessionStateEnum::IN_PROCESS->value);

            data_set($modelData, 'group_id', $warehouse->group_id);
            data_set($modelData, 'organisation_id', $warehouse->organisation_id);
            data_set($modelData, 'user_id', request()->user()->id);

            /** @var PickingSession $pickingSession */
            $pickingSession = $warehouse->pickingSessions()->create($modelData);

            $pickingSession->deliveryNotes()->attach($validDeliveryNoteIds, [
                'organisation_id' => $pickingSession->organisation_id,
                'group_id'        => $pickingSession->group_id
            ]);

            $pickingSession->refresh();

            $deliveryNotes = $pickingSession->deliveryNotes;

            $numberItems = 0;
            $numberDeliveryNotes = 0;
            foreach ($deliveryNotes as $deliveryNote) {
                $numberDeliveryNotes++;
                if($queued) {
                    StartHandlingDeliveryNote::make()->action($deliveryNote, request()->user());
                } else {
                    UpdateDeliveryNoteStateToInQueue::make()->action($deliveryNote, request()->user());
                }

                foreach ($deliveryNote->deliveryNoteItems as $item) {
                    $numberItems++;
                    UpdateDeliveryNoteItem::make()->action($item, [
                        'picking_session_id' => $pickingSession->id
                    ]);
                }
            }

            $pickingSession->updateQuietly([
                'number_items' => $numberItems,
                'number_delivery_notes' => $numberDeliveryNotes,
            ]);


            return $pickingSession;
        });

        return $pickingSession;
    }

    public function rules(): array
    {
        $rules = [
            'delivery_notes' => ['required', 'array'],
        ];

        if (!$this->strict) {
            $rules['state'] = [
                'sometimes',
                'required',
                new Enum(PickingSessionStateEnum::class)
            ];
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, ActionRequest $request): PickingSession
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inQueued(Warehouse $warehouse, ActionRequest $request): PickingSession
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->validatedData, true);
    }

    public function htmlResponse(PickingSession $pickingSession, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.dispatching.picking_sessions.show', [
            'organisation'   => $pickingSession->organisation->slug,
            'warehouse'      => $pickingSession->warehouse->slug,
            'pickingSession' => $pickingSession->slug,
        ]);
    }

}
