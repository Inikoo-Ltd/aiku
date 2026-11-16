<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Api\Group\Locations\WithMobileLocationsAccess;
use App\Actions\Dispatching\PickedBay\Json\ListAvailablePickedBays;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickedBay;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobilePickedBays
{
    use AsController;
    use WithMobileLocationsAccess;

    /**
     * @return array<int, array{id: int, code: string, number_delivery_notes: int, number_waiting: int, delivery_notes: array<int, array{id: int, reference: string, state: string, state_label: string}>}>
     */
    public function handle(Warehouse $warehouse): array
    {
        $this->useWebTableParameters([]);

        $pickedBays = EloquentCollection::make($this->allWebPages(fn () => ListAvailablePickedBays::make()->handle($warehouse))->all())
            ->load(['deliveryNotes' => fn ($query) => $query->orderBy('delivery_notes.reference')]);

        return $pickedBays
            ->map(fn (PickedBay $pickedBay) => [
                'id'                    => $pickedBay->id,
                'code'                  => $pickedBay->code,
                'number_delivery_notes' => $pickedBay->deliveryNotes->count(),
                'number_waiting'        => $pickedBay->deliveryNotes->where('state', DeliveryNoteStateEnum::HANDLING_BLOCKED)->count(),
                'delivery_notes'        => $pickedBay->deliveryNotes
                    ->map(fn (DeliveryNote $deliveryNote) => [
                        'id'          => $deliveryNote->id,
                        'reference'   => $deliveryNote->reference,
                        'state'       => $deliveryNote->state->value,
                        'state_label' => DeliveryNoteStateEnum::labels()[$deliveryNote->state->value],
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($warehouse->group_id === $user->group_id && ListAvailablePickedBays::canViewDispatchingEquipment($user, $warehouse), 403);

        return response()->json(['data' => $this->handle($warehouse)]);
    }
}
