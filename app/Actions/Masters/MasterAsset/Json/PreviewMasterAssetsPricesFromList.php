<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-24 13:55
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\Masters\MasterAsset\UpdateMasterAssetsPricesFromList;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * Matches the codes of a pasted price list to the master products of the master shop
 * and shows, per product, the current and the new price/RRP of every pasted currency.
 * Products of the shop that are outside the page's family or department are flagged
 * so the user can leave them out.
 */
class PreviewMasterAssetsPricesFromList extends OrgAction
{
    use WithMastersEditAuthorisation;

    public function handle(MasterShop $masterShop, ?MasterProductCategory $scope, array $rows): array
    {
        $priceExchanges = $masterShop->price_exchanges ?? [];

        $codes = collect($rows)->pluck('code')->map(fn (string $code) => mb_strtolower(trim($code)))->unique()->values();

        $masterAssets = MasterAsset::where('master_shop_id', $masterShop->id)
            ->whereIn(DB::raw('lower(code)'), $codes)
            ->with(['masterShop', 'masterFamily:id,code'])
            ->orderByDesc('is_main')
            ->orderByDesc('status')
            ->get()
            ->unique(fn (MasterAsset $masterAsset) => mb_strtolower($masterAsset->code))
            ->keyBy(fn (MasterAsset $masterAsset) => mb_strtolower($masterAsset->code));

        $pastedCurrencies = collect($rows)
            ->flatMap(fn (array $row) => array_merge(
                array_keys($row['master_prices'] ?? []),
                array_keys($row['master_rrps'] ?? []),
                array_keys($row['master_rrps_per_unit'] ?? [])
            ))
            ->unique()
            ->values();

        $codeCounts = collect($rows)->countBy(fn (array $row) => mb_strtolower(trim($row['code'])));

        return [
            'currencies'         => $pastedCurrencies->filter(fn (string $code) => isset($priceExchanges[$code]))->values(),
            'unknown_currencies' => $pastedCurrencies->reject(fn (string $code) => isset($priceExchanges[$code]))->values(),
            'rows'               => collect($rows)->map(function (array $row) use ($masterAssets, $priceExchanges, $scope, $codeCounts) {
                $code        = mb_strtolower(trim($row['code']));
                $masterAsset = $masterAssets->get($code);

                if (!$masterAsset) {
                    return [
                        'input_code' => trim($row['code']),
                        'found'      => false,
                        'duplicate'  => $codeCounts->get($code) > 1,
                    ];
                }

                $values  = UpdateMasterAssetsPricesFromList::newValues($masterAsset, $row, $priceExchanges);
                $changes = [];
                foreach ($values as $field => $currencyValues) {
                    foreach ($currencyValues as $currencyCode => $newValue) {
                        $currentValue = data_get($masterAsset->$field, "$currencyCode.value");
                        $changes[$field][$currencyCode] = [
                            'current' => $currentValue !== null ? (float) $currentValue : null,
                            'new'     => $newValue,
                            'changed' => $currentValue === null || round((float) $currentValue, 2) !== $newValue,
                        ];
                    }
                }

                return [
                    'input_code'    => trim($row['code']),
                    'found'         => true,
                    'duplicate'     => $codeCounts->get($code) > 1,
                    'id'            => $masterAsset->id,
                    'slug'          => $masterAsset->slug,
                    'code'          => $masterAsset->code,
                    'name'          => $masterAsset->name,
                    'units'         => (float) $masterAsset->units,
                    'status'        => (bool) $masterAsset->status,
                    'family_code'   => $masterAsset->masterFamily?->code,
                    'in_scope'      => $this->isInScope($masterAsset, $scope),
                    'changes'       => $changes,
                    'has_changes'   => collect($changes)->flatten(1)->contains(fn (array $change) => $change['changed']),
                    'values'        => [
                        'master_prices'        => $row['master_prices'] ?? [],
                        'master_rrps'          => $row['master_rrps'] ?? [],
                        'master_rrps_per_unit' => $row['master_rrps_per_unit'] ?? [],
                    ],
                ];
            })->values(),
        ];
    }

    private function isInScope(MasterAsset $masterAsset, ?MasterProductCategory $scope): bool
    {
        if (!$scope) {
            return true;
        }

        return match ($scope->type) {
            MasterProductCategoryTypeEnum::FAMILY         => $masterAsset->master_family_id === $scope->id,
            MasterProductCategoryTypeEnum::SUB_DEPARTMENT => $masterAsset->master_sub_department_id === $scope->id,
            default                                       => $masterAsset->master_department_id === $scope->id,
        };
    }

    public function rules(): array
    {
        return [
            'rows'                          => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.code'                   => ['required', 'string', 'max:255'],
            'rows.*.master_prices'          => ['sometimes', 'array'],
            'rows.*.master_prices.*'        => ['nullable', 'numeric', 'gt:0'],
            'rows.*.master_rrps'            => ['sometimes', 'array'],
            'rows.*.master_rrps.*'          => ['nullable', 'numeric', 'gt:0'],
            'rows.*.master_rrps_per_unit'   => ['sometimes', 'array'],
            'rows.*.master_rrps_per_unit.*' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): array
    {
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory->masterShop, $masterProductCategory, $this->validatedData['rows']);
    }

    public function inMasterShop(MasterShop $masterShop, ActionRequest $request): array
    {
        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->handle($masterShop, null, $this->validatedData['rows']);
    }
}
