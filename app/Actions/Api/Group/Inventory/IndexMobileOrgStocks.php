<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-09h-42m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

use App\Actions\Inventory\OrgStock\UI\IndexOrgStocks;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileOrgStocks
{
    use AsController;
    use WithMobileOrgStocks;

    public const array BUCKETS = ['current', 'discontinued', 'abnormality', 'all'];
    public const array SORTS = ['code', 'name', 'stock_value', 'quantity_available', 'family_code'];

    /**
     * @param array{bucket?: string|null, family_id?: int|null, q?: string|null, sort?: string|null, per_page?: int|null, page?: int|null} $filters
     */
    public function handle(Warehouse $warehouse, ?OrgStockFamily $family, array $filters): LengthAwarePaginator
    {
        $bucket = $filters['bucket'] ?? 'current';

        $this->useWebTableParameters($filters);
        request()->merge(['elements' => $bucket === 'all' ? ['state' => implode(',', OrgStockStateEnum::values())] : []]);

        $webTable = IndexOrgStocks::make();

        if ($family) {
            return $webTable->handle($family, null, $bucket, $warehouse);
        }

        return $webTable->initialisationFromWarehouse($warehouse, [])->handle($warehouse->organisation, null, $bucket, $warehouse);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(LengthAwarePaginator $orgStocks): array
    {
        $listed = collect($orgStocks->items());
        $skos   = OrgStock::with(['orgStockFamily', 'tradeUnits.image'])->findMany($listed->pluck('id'))->keyBy('id');

        return $listed->map(function (OrgStock $row) use ($skos) {
            $sko    = $skos->get($row->id);
            $family = $sko?->orgStockFamily;

            return [
                'id'               => $row->id,
                'slug'             => $row->slug,
                'code'             => $row->code,
                'name'             => $row->name,
                'state'            => $row->state->value,
                'state_label'      => OrgStockStateEnum::labels()[$row->state->value],
                'family'           => $family ? ['id' => $family->id, 'code' => $family->code] : null,
                'image_url'        => $sko ? $this->orgStockImageUrl($sko) : null,
                ...$this->quantityFields('quantity_available', $row->quantity_available),
                ...$this->quantityFields('quantity_in_locations', $row->getAttribute('quantity_in_locations')),
                'stock_value'      => (float) $row->getAttribute('stock_value'),
                'number_locations' => (int) $row->getAttribute('number_locations'),
                'last_seen_at'     => $row->discontinued_in_organisation_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewInventory($user, $warehouse);

        $filters = Validator::make($request->query(), [
            'bucket'    => ['sometimes', 'nullable', Rule::in(self::BUCKETS)],
            'family_id' => ['sometimes', 'nullable', 'bail', 'integer', 'min:1', 'max:'.self::MAX_ID, Rule::exists('org_stock_families', 'id')->where('organisation_id', $warehouse->organisation_id)->whereNull('deleted_at')],
            'q'         => ['sometimes', 'nullable', 'string', 'max:64'],
            'sort'      => ['sometimes', 'nullable', Rule::in(collect(self::SORTS)->flatMap(fn (string $sort) => [$sort, '-'.$sort])->all())],
            'per_page'  => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::WEB_MAX_PER_PAGE],
            'page'      => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $family    = empty($filters['family_id']) ? null : OrgStockFamily::findOrFail($filters['family_id']);
        $orgStocks = $this->handle($warehouse, $family, $filters);
        $stats     = $family ? $family->stats : $warehouse->organisation->inventoryStats;

        return response()->json([
            'data'          => $this->rows($orgStocks),
            'meta'          => $this->paginationMeta($orgStocks),
            'counts'        => [
                'current'      => (int) $stats?->number_current_org_stocks,
                'discontinued' => (int) $stats?->number_org_stocks_state_discontinued,
                'abnormality'  => (int) $stats?->number_org_stocks_state_abnormality,
                'all'          => (int) $stats?->number_org_stocks,
            ],
            'currency_code' => $warehouse->organisation->currency->code,
        ]);
    }
}
