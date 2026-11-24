<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-24 13:55
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\ActionRequest;

/**
 * Applies prices and RRPs pasted from a price list, each product with its own values.
 * RRPs given per unit are multiplied by the product's units (not in dropshipping, where
 * the RRP is already per product). Every pasted value counts as typed by the user, so
 * it goes through UpdateBulkMasterAssetsPrices with its hand-set rules.
 */
class UpdateMasterAssetsPricesFromList extends OrgAction
{
    use WithMastersEditAuthorisation;

    /**
     * @param array{master_prices?: array<string, float|string|null>, master_rrps?: array<string, float|string|null>, master_rrps_per_unit?: array<string, float|string|null>} $row
     * @return array{master_prices: array<string, float>, master_rrps: array<string, float>}
     */
    public static function newValues(MasterAsset $masterAsset, array $row, array $priceExchanges): array
    {
        $isDropship = $masterAsset->masterShop->type == ShopTypeEnum::DROPSHIPPING;

        $values = ['master_prices' => [], 'master_rrps' => []];

        foreach ($row['master_prices'] ?? [] as $currencyCode => $value) {
            if ($value !== null && $value !== '' && isset($priceExchanges[$currencyCode])) {
                $values['master_prices'][$currencyCode] = round((float) $value, 2);
            }
        }

        foreach ($row['master_rrps'] ?? [] as $currencyCode => $value) {
            if ($value !== null && $value !== '' && isset($priceExchanges[$currencyCode])) {
                $values['master_rrps'][$currencyCode] = round((float) $value, 2);
            }
        }

        foreach ($row['master_rrps_per_unit'] ?? [] as $currencyCode => $value) {
            if ($value !== null && $value !== '' && isset($priceExchanges[$currencyCode])) {
                $units                                  = $isDropship ? 1 : max((float) $masterAsset->units, 1);
                $values['master_rrps'][$currencyCode] = round((float) $value * $units, 2);
            }
        }

        return $values;
    }

    /**
     * @param list<array{id: int, master_prices?: array, master_rrps?: array, master_rrps_per_unit?: array}> $rows
     * @return array{updated: int, skipped: array<string, list<string>>}
     */
    public function handle(array $rows): array
    {
        $rowsById     = collect($rows)->keyBy('id');
        $masterAssets = MasterAsset::whereIn('id', $rowsById->keys())
            ->where('group_id', $this->group->id)
            ->with('masterShop')
            ->get();

        $updated = 0;
        $skipped = [];

        foreach ($masterAssets as $masterAsset) {
            $values = static::newValues($masterAsset, $rowsById->get($masterAsset->id), $masterAsset->masterShop?->price_exchanges ?? []);

            $modelData = ['ids' => [$masterAsset->id], 'rrp_per_unit' => false];
            foreach ($values as $field => $currencyValues) {
                foreach ($currencyValues as $currencyCode => $value) {
                    $modelData[$field][$currencyCode] = ['value' => $value, 'independent' => true];
                }
            }

            if (!isset($modelData['master_prices']) && !isset($modelData['master_rrps'])) {
                continue;
            }

            $result  = UpdateBulkMasterAssetsPrices::make()->action($modelData);
            $updated += $result['updated'];
            $skipped = array_merge($skipped, $result['skipped']);
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    public function rules(): array
    {
        return [
            'rows'                          => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.id'                     => ['required', 'integer', 'exists:master_assets,id'],
            'rows.*.master_prices'          => ['sometimes', 'array'],
            'rows.*.master_prices.*'        => ['nullable', 'numeric', 'gt:0'],
            'rows.*.master_rrps'            => ['sometimes', 'array'],
            'rows.*.master_rrps.*'          => ['nullable', 'numeric', 'gt:0'],
            'rows.*.master_rrps_per_unit'   => ['sometimes', 'array'],
            'rows.*.master_rrps_per_unit.*' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    public function asController(ActionRequest $request): void
    {
        $this->initialisationFromGroup(group(), $request);

        $this->handle($this->validatedData['rows']);
    }

    /**
     * @return array{updated: int, skipped: array<string, list<string>>}
     */
    public function action(array $modelData): array
    {
        $this->asAction = true;
        $this->initialisationFromGroup(group(), $modelData);

        return $this->handle($this->validatedData['rows']);
    }
}
