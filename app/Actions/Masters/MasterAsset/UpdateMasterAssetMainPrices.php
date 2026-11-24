<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-17 10:31
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\MasterAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Saves the price and RRP of a master product in its main currencies only. Every other currency of the
 * master shop follows its main currency by the shop exchange rate and rounding, unless it was set by
 * hand, which is left alone, the same way the master prices tool saves.
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

        return UpdateMasterAssetPrices::make()->action($masterAsset, $payload);
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
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $mainCurrencies = GetMasterShopCurrenciesRate::majorCurrencyCodes($this->masterAsset->masterShop);

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
