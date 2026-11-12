<?php

/*
 * Author Andi Ferdiawan
 * Created on 11-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Locations;

use App\Actions\Inventory\WarehouseArea\UI\IndexWarehouseAreas;
use App\Http\Resources\Inventory\WarehouseAreaResource;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileWarehouseAreas
{
    use AsController;
    use WithMobileLocationsAccess;

    public const array SORTS = ['code', 'name', 'number_locations', 'picking_position', 'stock_value', 'number_empty_locations'];

    /**
     * Every area of the warehouse from the web areas table (IndexWarehouseAreas), read page
     * by page. The web resource turns an unset picking position into 0, so the app also gets
     * the raw value to tell "no picking order" apart from order 0.
     *
     * @param array{q?: string|null, sort?: string|null} $filters
     * @return array<int, array<string, mixed>>
     */
    public function handle(Warehouse $warehouse, array $filters = []): array
    {
        $this->useWebTableParameters($filters);

        return $this->allWebPages(fn () => IndexWarehouseAreas::make()->handle($warehouse))
            ->map(fn (WarehouseArea $area) => array_merge(
                WarehouseAreaResource::make($area)->resolve(),
                ['picking_position' => $area->picking_position === null ? null : (float) $area->picking_position]
            ))
            ->all();
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewLocations($user, $warehouse);

        $sorts = collect(self::SORTS)->flatMap(fn (string $sort) => [$sort, '-'.$sort])->all();

        $filters = Validator::make($request->query(), [
            'q'    => ['sometimes', 'nullable', 'string', 'max:64'],
            'sort' => ['sometimes', 'nullable', Rule::in($sorts)],
        ])->validate();

        return response()->json(['data' => $this->handle($warehouse, $filters)]);
    }
}
