<?php

/*
 * Author Andi Ferdiawan
 * Created on 12-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

use App\Actions\Inventory\OrgStock\UI\IndexOrgStocks;
use App\Http\Resources\Inventory\OrgStocksResource;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileOrgStockFamilySkos
{
    use AsController;
    use WithMobileInventoryAccess;

    public const array FIELDS = ['id', 'slug', 'code', 'name', 'quantity_in_locations', 'quantity_available', 'stock_value', 'number_locations', 'stock_cover', 'woc'];

    /**
     * The SKOs tab of the web family page (IndexOrgStocks for a family, current bucket),
     * with the family summary the page heads it with.
     */
    public function asController(Warehouse $warehouse, OrgStockFamily $orgStockFamily, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewInventory($user, $warehouse);
        abort_unless($orgStockFamily->organisation_id === $warehouse->organisation_id, 404);

        $filters = Validator::make($request->query(), [
            'q'        => ['sometimes', 'nullable', 'string', 'max:64'],
            'bucket'   => ['sometimes', 'nullable', Rule::in(['current', 'discontinued', 'all'])],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::WEB_MAX_PER_PAGE],
            'page'     => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $this->useWebTableParameters($filters);
        $skos  = IndexOrgStocks::make()->handle($orgStockFamily, null, $filters['bucket'] ?? 'current');
        $stats = $orgStockFamily->stats;

        return response()->json([
            'family' => [
                'id'                        => $orgStockFamily->id,
                'code'                      => $orgStockFamily->code,
                'name'                      => $orgStockFamily->name,
                'state'                     => $orgStockFamily->state->value,
                'number_current_org_stocks' => (int) $stats?->number_current_org_stocks,
                'stock_value'               => (float) $stats?->stock_value,
                'currency_code'             => $warehouse->organisation->currency->code,
            ],
            'data'   => collect($skos->items())->map(fn (OrgStock $orgStock) => array_merge(
                Arr::only(OrgStocksResource::make($orgStock)->resolve(), self::FIELDS),
                ['state' => $orgStock->state->value]
            ))->values()->all(),
            'meta'   => $this->paginationMeta($skos),
        ]);
    }
}
