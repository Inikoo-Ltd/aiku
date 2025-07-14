<?php

/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\PickingSession;

use App\Actions\Dispatching\PickingSessionItem\StorePickingSessionItem;
use App\Actions\OrgAction;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Enum;
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
    public function handle(Warehouse $warehouse, array $modelData): PickingSession
    {
        $deliveryNoteIds = Arr::pull($modelData, 'delivery_notes');
        $reference = 'PS-'. $warehouse->pickingSessions()->max('id') + 1;

        data_set($modelData, 'group_id', $warehouse->group_id);
        data_set($modelData, 'organisation_id', $warehouse->organisation_id);
        data_set($modelData, 'reference', $reference);
        data_set($modelData, 'user_id', request()->user()->id);
        data_set($modelData, 'start_at', now());

        $pickingSession = $warehouse->pickingSessions()->create($modelData);

        $pickingSession->deliveryNotes()->attach($deliveryNoteIds);

        $pickingSession->refresh();

        $mergedItems = $this->getMergedDeliveryNoteItems($pickingSession);

        foreach ($mergedItems as $item) {
            StorePickingSessionItem::dispatch($pickingSession, $item);
        }
        return $pickingSession;
    }

    public function getMergedDeliveryNoteItems(PickingSession $pickingSession): array
    {
        $deliveryNotes = $pickingSession->deliveryNotes()->with('deliveryNoteItems')->get();

        $mergedItems = [];

        foreach ($deliveryNotes as $deliveryNote) {

            foreach ($deliveryNote->deliveryNoteItems as $item) {
                $orgStockId = $item->org_stock_id;

                if (isset($mergedItems[$orgStockId])) {
                    $mergedItems[$orgStockId]['quantity_required'] += $item->quantity;
                } else {
                    $mergedItems[$orgStockId] = [
                        'org_stock_id' => $orgStockId->id,
                        'quantity_required' => $item->quantity
                    ];
                }
            }
        }

        return array_values($mergedItems);
    }

    public function rules(): array
    {
        $rules = [
            'delivery_notes'  => ['required', 'array'],
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

    public function asController(Warehouse $warehouse, ActionRequest $request)
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->validatedData);
    }
}
