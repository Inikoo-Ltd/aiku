<?php

/*
 * Author Andi Ferdiawan
 * Created on 11-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Locations;

use App\Actions\Inventory\LocationOrgStock\UI\IndexEmptyLocationOrgStocks;
use App\Http\Resources\Inventory\EmptyLocationOrgStocksResource;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileEmptyLocationOrgStocks
{
    use AsController;
    use WithMobileLocationsAccess;

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewLocations($user, $warehouse);

        $filters = Validator::make($request->query(), [
            'q'        => ['sometimes', 'nullable', 'string', 'max:64'],
            'sort'     => ['sometimes', 'nullable', Rule::in(['code', '-code', 'name', '-name', 'location_code', '-location_code'])],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::WEB_MAX_PER_PAGE],
            'page'     => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $this->useWebTableParameters($filters);
        $rows = IndexEmptyLocationOrgStocks::make()->handle($warehouse, '');

        return response()->json([
            'data' => EmptyLocationOrgStocksResource::collection($rows->items())->resolve(),
            'meta' => $this->paginationMeta($rows),
        ]);
    }
}
