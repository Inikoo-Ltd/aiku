<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Api\Group\Locations\WithMobileLocationsAccess;
use App\Actions\Dispatching\Picking\Picker\Json\GetPickerUsers;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobilePickers
{
    use AsController;
    use WithMobileLocationsAccess;

    /**
     * @return Collection<int, array{user_id: int, name: string|null, alias: string|null}>
     */
    public function pickers(Warehouse $warehouse): Collection
    {
        $this->useWebTableParameters([]);

        return $this->allWebPages(fn () => GetPickerUsers::make()->handle($warehouse->organisation))
            ->map(fn ($picker) => [
                'user_id' => (int) $picker->id,
                'name'    => $picker->contact_name,
                'alias'   => $picker->alias,
            ])
            ->unique('user_id')
            ->values();
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($warehouse->group_id === $user->group_id && GetPickerUsers::canViewOrganisationDispatching($user, $warehouse->organisation), 403);

        return response()->json(['data' => $this->pickers($warehouse)->all()]);
    }
}
