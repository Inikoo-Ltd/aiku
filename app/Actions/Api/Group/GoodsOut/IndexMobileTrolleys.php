<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Api\Group\Locations\WithMobileLocationsAccess;
use App\Actions\Dispatching\Trolley\Json\ListAvailableTrolleys;
use App\Actions\Dispatching\Trolley\UI\IndexTrolleys;
use App\Models\Dispatching\Trolley;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileTrolleys
{
    use AsController;
    use WithMobileLocationsAccess;

    /**
     * @return array<int, array{id: int, name: string, current_delivery_note: array{id: int, reference: string}|null, current_picking_session: array{id: int, reference: string}|null}>
     */
    public function handle(Warehouse $warehouse, bool $onlyAvailable): array
    {
        $this->useWebTableParameters([]);

        $trolleys = $onlyAvailable
            ? $this->allWebPages(fn () => ListAvailableTrolleys::make()->handle($warehouse))
            : $this->allWebPages(fn () => IndexTrolleys::make()->handle($warehouse));

        return $trolleys
            ->map(fn (Trolley $trolley) => [
                'id'                      => $trolley->id,
                'name'                    => $trolley->name,
                'current_delivery_note'   => $trolley->current_delivery_note_id && $trolley->deliveryNote ? [
                    'id'        => $trolley->deliveryNote->id,
                    'reference' => $trolley->deliveryNote->reference,
                ] : null,
                'current_picking_session' => $trolley->picking_session_id && $trolley->pickingSession ? [
                    'id'        => $trolley->pickingSession->id,
                    'reference' => $trolley->pickingSession->reference,
                ] : null,
            ])
            ->values()
            ->all();
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($warehouse->group_id === $user->group_id && ListAvailableTrolleys::canViewDispatchingEquipment($user, $warehouse), 403);

        $data = Validator::make($request->query(), [
            'available' => ['sometimes', 'nullable', 'boolean'],
        ])->validate();

        return response()->json(['data' => $this->handle($warehouse, (bool) ($data['available'] ?? false))]);
    }
}
