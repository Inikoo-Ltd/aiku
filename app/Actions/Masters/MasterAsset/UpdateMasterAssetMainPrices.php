<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-17 10:31
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\MasterAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Saves the price and RRP of a master product in its main currencies only. Every other currency of the
 * master shop follows its main currency by the shop exchange rate and rounding, unless it was set by
 * hand, which is left alone, the same way the master prices tool saves. The RRP per unit can also be
 * copied to other single-stock master products selling the same stock, in the main currencies they share.
 */
class UpdateMasterAssetMainPrices extends OrgAction
{
    use WithMastersEditAuthorisation;

    private MasterAsset $masterAsset;

    public function handle(MasterAsset $masterAsset, array $modelData): MasterAsset
    {
        $exchanges = $masterAsset->masterShop?->price_exchanges ?? [];
        $payload   = [];

        foreach (['master_prices', 'master_rrps'] as $field) {
            $mainValues = array_filter($modelData[$field] ?? [], fn ($value) => $value !== null);
            if ($mainValues) {
                $payload[$field] = self::withFollowingCurrencies($masterAsset->$field ?? [], $mainValues, $exchanges);
            }
        }

        if (!$payload) {
            return $masterAsset;
        }

        $masterAsset = UpdateMasterAssetPrices::make()->action($masterAsset, $payload);

        $copiedRrps = array_filter($modelData['master_rrps'] ?? [], fn ($value) => $value !== null);
        if ($copiedRrps && !empty($modelData['copy_rrps_to'])) {
            $this->copyRrpsPerUnit($masterAsset, $copiedRrps, $modelData['copy_rrps_to']);
        }

        return $masterAsset;
    }

    /**
     * @param  array<string, float|int|string>  $rrps  RRP per outer of the saved master product by main currency code
     * @param  array<int, int>  $masterAssetIds
     */
    private function copyRrpsPerUnit(MasterAsset $masterAsset, array $rrps, array $masterAssetIds): void
    {
        $units    = (float) $masterAsset->units ?: 1;
        $stockIds = $masterAsset->stocks()->pluck('stocks.id');

        if ($stockIds->count() !== 1) {
            return;
        }

        MasterAsset::query()
            ->whereIn('id', $masterAssetIds)
            ->where('id', '!=', $masterAsset->id)
            ->where('group_id', $masterAsset->group_id)
            ->whereHas('stocks', fn (Builder $query) => $query->whereIn('stocks.id', $stockIds))
            ->has('stocks', '=', 1)
            ->with('masterShop')
            ->get()
            ->each(function (MasterAsset $otherMasterAsset) use ($rrps, $units) {
                $otherMainCurrencies = collect($otherMasterAsset->masterShop?->price_exchanges ?? [])
                    ->filter(fn (array $exchange) => $exchange['is_major'] ?? false)
                    ->keys()
                    ->all();
                $otherUnits = (float) $otherMasterAsset->units ?: 1;

                $otherRrps = collect($rrps)
                    ->only($otherMainCurrencies)
                    ->map(fn ($rrp) => round((float) $rrp / $units * $otherUnits, 2))
                    ->all();

                if ($otherRrps) {
                    $this->handle($otherMasterAsset, ['master_rrps' => $otherRrps]);
                }
            });
    }

    /**
     * @param  array<string, float|int|string>  $mainValues  value by main currency code
     *
     * @return array<string, array{value: float}>
     */
    public static function withFollowingCurrencies(array $current, array $mainValues, array $exchanges): array
    {
        $payload = [];
        foreach ($mainValues as $currencyCode => $value) {
            $payload[$currencyCode] = ['value' => (float) $value];
        }

        foreach ($exchanges as $currencyCode => $exchange) {
            $mainCurrency = $exchange['major'] ?? null;
            if (($exchange['is_major'] ?? false) || !isset($payload[$mainCurrency]) || !($exchange['exchange'] ?? null) || data_get($current, "$currencyCode.independent")) {
                continue;
            }

            $payload[$currencyCode] = ['value' => (float) formatPrice(
                $payload[$mainCurrency]['value'],
                (float) $exchange['exchange'],
                (int) ($exchange['fraction_digits'] ?? 2),
                isset($exchange['increment']) ? (float) $exchange['increment'] : null
            )];
        }

        return $payload;
    }

    public function rules(): array
    {
        return [
            'master_prices'   => ['sometimes', 'array'],
            'master_prices.*' => ['nullable', 'numeric', 'gt:0'],
            'master_rrps'     => ['sometimes', 'array'],
            'master_rrps.*'   => ['nullable', 'numeric', 'gt:0'],
            'copy_rrps_to'    => ['sometimes', 'array'],
            'copy_rrps_to.*'  => ['integer'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $mainCurrencies = collect($this->masterAsset->masterShop?->price_exchanges ?? [])
            ->filter(fn (array $exchange) => $exchange['is_major'] ?? false)
            ->keys();

        foreach (['master_prices', 'master_rrps'] as $field) {
            $notMain = collect(array_keys((array) $this->get($field, [])))->diff($mainCurrencies);
            if ($notMain->isNotEmpty()) {
                $validator->errors()->add($field, __('Only the main currencies can be set here (:currencies), the others follow by exchange rate', ['currencies' => $mainCurrencies->implode(', ')]));
            }
        }
    }

    public function action(MasterAsset $masterAsset, array $modelData): MasterAsset
    {
        $this->asAction    = true;
        $this->masterAsset = $masterAsset;
        $this->initialisationFromGroup($masterAsset->group, $modelData);

        return $this->handle($masterAsset, $this->validatedData);
    }

    public function asController(MasterAsset $masterAsset, ActionRequest $request): MasterAsset
    {
        abort_unless($masterAsset->group_id === group()->id, 404);
        $this->masterAsset = $masterAsset;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterAsset, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
