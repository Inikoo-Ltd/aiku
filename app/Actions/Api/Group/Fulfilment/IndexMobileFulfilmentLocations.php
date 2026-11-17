<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileFulfilmentLocations
{
    use AsController;
    use WithMobileStoredPallets;

    public const int LIMIT = 20;

    /**
     * @return array<int, array{id: int, code: string, number_pallets: int}>
     */
    public function handle(Warehouse $warehouse, ?string $search): array
    {
        $search = trim((string) $search);

        return Location::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('status', LocationStatusEnum::OPERATIONAL)
            ->where('allow_fulfilment', true)
            ->when($search !== '', fn (Builder $query) => $query->whereStartWith('locations.code', $search))
            ->select(['locations.id', 'locations.code'])
            ->withCount(['pallets as number_pallets' => fn (Builder $query) => $query->whereIn('pallets.status', PalletStatusEnum::occupyingLocation())])
            ->orderBy('code')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Location $location) => [
                'id'             => $location->id,
                'code'           => $location->code,
                'number_pallets' => (int) $location->getAttribute('number_pallets'),
            ])
            ->all();
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureFulfilmentAccess($user, $warehouse, self::canMovePallets($user, $warehouse), __('You can not choose pallet locations in this warehouse.'));

        $validated = Validator::make($request->query(), [
            'q' => ['sometimes', 'nullable', 'string', 'max:64'],
        ])->validate();

        return response()->json(['data' => $this->handle($warehouse, $validated['q'] ?? null)]);
    }
}
