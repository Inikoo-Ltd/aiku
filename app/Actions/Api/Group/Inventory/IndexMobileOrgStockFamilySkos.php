<?php

/*
 * Author Andi Ferdiawan
 * Created on 12-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

use App\Actions\Inventory\OrgStock\UI\IndexOrgStocks;
use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
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
    use WithMobileStockFractions;

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
        $skos     = IndexOrgStocks::make()->handle($orgStockFamily, null, $filters['bucket'] ?? 'current', $warehouse);
        $stats    = $orgStockFamily->stats;
        $packedIn = OrgStock::query()->whereIn('id', collect($skos->items())->pluck('id'))->pluck('packed_in', 'id');

        return response()->json([
            'family' => [
                'id'                        => $orgStockFamily->id,
                'code'                      => $orgStockFamily->code,
                'name'                      => $orgStockFamily->name,
                'state'                     => $orgStockFamily->state->value,
                'number_current_org_stocks' => (int) $stats?->number_current_org_stocks,
                'stock_value'               => (float) $stats?->stock_value,
                'currency_code'             => $warehouse->organisation->currency->code,
                'sko_status'                => $this->skoStatus($orgStockFamily),
                'quantity_status'           => $this->quantityStatus($orgStockFamily),
            ],
            'data'   => collect($skos->items())->map(fn (OrgStock $orgStock) => array_merge(
                Arr::only(OrgStocksResource::make($orgStock)->resolve(), self::FIELDS),
                ['state' => $orgStock->state->value],
                $this->quantityFields('quantity_available', $orgStock->getAttribute('quantity_available'), $packedIn->get($orgStock->id)),
                $this->quantityFields('quantity_in_locations', $orgStock->getAttribute('quantity_in_locations'), $packedIn->get($orgStock->id)),
            ))->values()->all(),
            'meta'   => $this->paginationMeta($skos),
        ]);
    }

    /**
     * The "SKO Status" box of the web family page (GetOrgStockFamilyShowcase stock_counts).
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function skoStatus(OrgStockFamily $orgStockFamily): array
    {
        $stats = $orgStockFamily->stats;

        return [
            ['key' => 'active', 'label' => __('Active'), 'count' => (int) $stats?->number_org_stocks_state_active],
            ['key' => 'discontinuing', 'label' => __('Discontinuing'), 'count' => (int) $stats?->number_org_stocks_state_discontinuing],
            ['key' => 'discontinued', 'label' => __('Discontinued'), 'count' => (int) $stats?->number_org_stocks_state_discontinued],
            ['key' => 'suspended', 'label' => __('Suspended'), 'count' => (int) $stats?->number_org_stocks_state_suspended],
        ];
    }

    /**
     * The "Quantity Status" box of the web family page: the organisation's stock cover buckets for this family.
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function quantityStatus(OrgStockFamily $orgStockFamily): array
    {
        return collect(GetOrganisationStockCoverBuckets::run($orgStockFamily->organisation, $orgStockFamily))
            ->map(fn (array $bucket) => ['key' => $bucket['bucket'], 'label' => $bucket['label'], 'count' => (int) $bucket['count']])
            ->values()
            ->all();
    }
}
