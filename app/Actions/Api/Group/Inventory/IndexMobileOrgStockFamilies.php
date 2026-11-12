<?php

/*
 * Author Andi Ferdiawan
 * Created on 12-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

use App\Actions\Inventory\OrgStockFamily\UI\IndexOrgStockFamilies;
use App\Http\Resources\Inventory\OrgStockFamiliesResource;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileOrgStockFamilies
{
    use AsController;
    use WithMobileInventoryAccess;

    public const array STATES = ['in_process', 'active', 'discontinuing', 'discontinued'];
    public const array FIELDS = ['slug', 'code', 'name', 'number_current_org_stocks', 'number_out_of_stock_org_stocks', 'currency_code', 'stock_value', 'potential_sales', 'on_the_way_po_value', 'on_the_way_po_count', 'stock_turn', 'stock_cover'];

    /**
     * The SKO families table of the web (IndexOrgStockFamilies, one bucket per state) and the
     * state counts its tabs show (the organisation's inventory stats). The table does not
     * select the family id or state, so those are added here.
     */
    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewInventory($user, $warehouse);

        $filters = Validator::make($request->query(), [
            'state'    => ['sometimes', 'nullable', Rule::in(self::STATES)],
            'q'        => ['sometimes', 'nullable', 'string', 'max:64'],
            'sort'     => ['sometimes', 'nullable', Rule::in(['code', '-code', 'name', '-name', 'stock_value', '-stock_value', 'number_current_org_stocks', '-number_current_org_stocks'])],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::WEB_MAX_PER_PAGE],
            'page'     => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $state = $filters['state'] ?? 'active';
        $this->useWebTableParameters($filters);
        $families = IndexOrgStockFamilies::make()->handle($warehouse->organisation, null, $state);
        $ids      = OrgStockFamily::whereIn('slug', collect($families->items())->pluck('slug'))->pluck('id', 'slug');
        $stats    = $warehouse->organisation->inventoryStats;

        return response()->json([
            'data'   => collect($families->items())->map(fn (OrgStockFamily $family) => array_merge(
                ['id' => $ids->get($family->slug), 'state' => $state],
                Arr::only(OrgStockFamiliesResource::make($family)->resolve(), self::FIELDS)
            ))->values()->all(),
            'meta'   => $this->paginationMeta($families),
            'counts' => [
                'in_process'    => (int) $stats?->number_org_stock_families_state_in_process,
                'active'        => (int) $stats?->number_org_stock_families_state_active,
                'discontinuing' => (int) $stats?->number_org_stock_families_state_discontinuing,
                'discontinued'  => (int) $stats?->number_org_stock_families_state_discontinued,
            ],
        ]);
    }
}
