<?php

/*
 * Author Andi Ferdiawan
 * Created on 12-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Locations;

use App\Actions\Inventory\OrgStockMovement\UI\IndexOrgStockMovements;
use App\Http\Resources\Inventory\OrgStockMovementsResource;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileLocationStockMovements
{
    use AsController;
    use WithMobileLocationsAccess;

    public const array FIELDS = ['id', 'date', 'type', 'type_label', 'flow', 'quantity', 'audited_quantity', 'running_quantity', 'reason', 'reason_label', 'note', 'org_stock_name', 'org_stock_slug', 'parent_type', 'parent_reference'];

    /**
     * The web location page's Stock movements tab (IndexOrgStockMovements and
     * OrgStockMovementsResource), newest first, with the SKO code and the user's name,
     * which that table does not give.
     */
    public function asController(Location $location, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewLocations($user, $location->warehouse);

        $filters = Validator::make($request->query(), [
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::WEB_MAX_PER_PAGE],
            'page'     => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $this->useWebTableParameters($filters);
        $movements = IndexOrgStockMovements::make()->handle($location);
        $codes     = OrgStock::whereIn('slug', collect($movements->items())->pluck('org_stock_slug')->filter()->unique())->pluck('code', 'slug');

        return response()->json([
            'data' => collect($movements->items())->map(fn (OrgStockMovement $movement) => array_merge(
                Arr::only(OrgStockMovementsResource::make($movement)->resolve(), self::FIELDS),
                [
                    'org_stock_code' => $codes->get($movement->getAttribute('org_stock_slug')),
                    'user_name'      => $movement->user?->contact_name ?: $movement->user?->username,
                ]
            ))->values()->all(),
            'meta' => $this->paginationMeta($movements),
        ]);
    }
}
