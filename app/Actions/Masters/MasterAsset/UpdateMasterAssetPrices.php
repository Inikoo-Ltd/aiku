<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Specialised save path for master price/rrp edits: cascades to child products with the
 * per-product cache-break job skipped, then breaks the webpages cache synchronously so
 * the user sees the new prices on the website immediately after saving.
 *
 * The write is a per-currency merge under a row lock: only currencies present in the
 * payload with a non-null value are touched, so a stale or partial payload can never
 * wipe other currencies, and a concurrent save or exchange-recalculation chunk cannot
 * be silently overwritten by a whole-column replace.
 */
class UpdateMasterAssetPrices extends OrgAction
{
    use WithActionUpdate;
    use WithMastersEditAuthorisation;

    public const int SYNC_CASCADE_MAX_PRODUCTS = 5;

    public bool $forceAsyncCascade = false;

    public function handle(MasterAsset $masterAsset, array $modelData): MasterAsset
    {
        $oldMasterAsset = clone($masterAsset);

        $masterAsset = DB::transaction(function () use ($masterAsset, $modelData) {
            /** @var MasterAsset $lockedMasterAsset */
            $lockedMasterAsset = MasterAsset::lockForUpdate()->findOrFail($masterAsset->id);

            foreach (['master_prices', 'master_rrps'] as $field) {
                if (!Arr::has($modelData, $field)) {
                    continue;
                }

                $record         = is_array($lockedMasterAsset->$field) ? $lockedMasterAsset->$field : [];
                $priceExchanges = $lockedMasterAsset->masterShop?->price_exchanges ?? [];

                foreach ($modelData[$field] as $currencyCode => $entry) {
                    if (($entry['value'] ?? null) !== null) {
                        data_set(
                            $record,
                            "$currencyCode.value",
                            $this->snapToRoundingPolicy(
                                $entry['value'],
                                $priceExchanges[$currencyCode] ?? null,
                                $entry['independent'] ?? data_get($record, "$currencyCode.independent", false)
                            )
                        );
                    }
                    if (array_key_exists('independent', $entry)) {
                        data_set($record, "$currencyCode.independent", (bool) $entry['independent']);
                    }
                }

                $modelData[$field] = $record;
            }

            $baseCurrencyCode = GetMasterShopCurrenciesRate::baseCurrencyCode(
                $lockedMasterAsset->masterShop?->price_exchanges ?? []
            );

            if ($basePrice = data_get($modelData, "master_prices.$baseCurrencyCode.value")) {
                data_set($modelData, 'price', $basePrice);
            }
            if ($baseRRP = data_get($modelData, "master_rrps.$baseCurrencyCode.value")) {
                data_set($modelData, 'rrp', $baseRRP);
            }

            return $this->update($lockedMasterAsset, $modelData);
        });

        $changedPrices = $masterAsset->wasChanged(['master_prices', 'price']);
        $changedRRPs   = $masterAsset->wasChanged(['master_rrps', 'rrp']);

        if ($changedPrices || $changedRRPs) {
            $type = $changedPrices && $changedRRPs ? 'both' : ($changedPrices ? 'price' : 'rrp');

            if (!$this->forceAsyncCascade && $masterAsset->products()->count() <= self::SYNC_CASCADE_MAX_PRODUCTS) {
                CascadeMasterAssetPricesToChildren::run($masterAsset, $type);
            } else {
                CascadeMasterAssetPricesToChildren::dispatch($masterAsset, $type);
            }
        }

        $masterAsset->auditEvent = 'updated_master_prices';
        $masterAsset->isCustomEvent = true;

        $oldAudit = [];
        $newAudit = [];

        if ($changedPrices) {
            $oldAudit = array_merge($oldAudit, $this->getMasterPricesAudit($oldMasterAsset->master_prices));
            $newAudit = array_merge($newAudit, $this->getMasterPricesAudit($masterAsset->master_prices));
        }

        if ($changedRRPs) {
            $oldAudit = array_merge($oldAudit, $this->getMasterPricesAudit($oldMasterAsset->master_rrps, 'RRP'));
            $newAudit = array_merge($newAudit, $this->getMasterPricesAudit($masterAsset->master_rrps, 'RRP'));
        }

        $masterAsset->auditCustomOld = $oldAudit;
        $masterAsset->auditCustomNew = $newAudit;

        Event::dispatch(new AuditCustom($masterAsset));

        return $masterAsset;
    }

    /**
     * The price editor derives minor-currency values from the major client-side and knows
     * nothing about the shop's rounding policy, so derived (non-independent) minors are
     * snapped here — the single write path — to the currency's fraction_digits/increment.
     * Majors and hand-set (independent) values are stored exactly as given.
     */
    private function snapToRoundingPolicy(mixed $value, ?array $exchangeConfig, mixed $independent): mixed
    {
        if (!$exchangeConfig || ($exchangeConfig['is_major'] ?? false) || $independent) {
            return $value;
        }

        $fractionDigits = (int)($exchangeConfig['fraction_digits'] ?? 2);
        $increment      = isset($exchangeConfig['increment']) ? (float)$exchangeConfig['increment'] : null;

        if ($fractionDigits >= 2 && !$increment) {
            return $value;
        }

        return formatPrice((float)$value, 1, $fractionDigits, $increment);
    }

    private function getMasterPricesAudit(array $masterPrices, string $type = 'Price'): array
    {
        $result = [];

        foreach ($masterPrices as $currency => $price) {
            $key = $type . ' ' . $currency;
            $result[$key] = $price['value'];

            if (!($currency === 'EUR' || $currency === 'GBP') && $price['independent']) {
                $result[$key] .= ' (Is Rebel)';
            }
        }

        return $result;
    }

    public function rules(): array
    {
        return [
            'master_prices'                => ['sometimes', 'array'],
            'master_prices.*.value'        => ['nullable', 'numeric', 'gt:0'],
            'master_prices.*.independent'  => ['sometimes', 'boolean'],
            'master_rrps'                  => ['sometimes', 'array'],
            'master_rrps.*.value'          => ['nullable', 'numeric', 'gt:0'],
            'master_rrps.*.independent'    => ['sometimes', 'boolean'],
        ];
    }

    public function asController(MasterAsset $masterAsset, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterAsset, $this->validatedData);
    }

    public function action(MasterAsset $masterAsset, array $modelData): MasterAsset
    {
        $this->asAction = true;
        $this->initialisationFromGroup($masterAsset->group, $modelData);

        return $this->handle($masterAsset, $this->validatedData);
    }
}
