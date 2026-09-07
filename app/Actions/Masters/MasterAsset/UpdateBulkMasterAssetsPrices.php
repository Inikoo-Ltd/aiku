<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * Applies the same per-currency prices/rrps to a selection of master assets.
 * RRP values arrive per unit and are multiplied by each asset's own units.
 * Null values and currencies whose existing entry is independent are left untouched,
 * so a bulk edit can never silently overwrite a hand-maintained price.
 */
class UpdateBulkMasterAssetsPrices extends OrgAction
{
    use WithMastersEditAuthorisation;

    /**
     * @return array{updated: int, skipped: array<string, list<string>>}
     */
    public function handle(array $masterAssetIDs, array $modelData): array
    {
        $rrpPerUnit = Arr::pull($modelData, 'rrp_per_unit', false);

        $updated = 0;
        $skipped = [];

        foreach (MasterAsset::whereIn('id', $masterAssetIDs)->where('group_id', $this->group->id)->get() as $masterAsset) {
            $dataToUpdate = [];

            foreach (['master_prices', 'master_rrps'] as $field) {
                if (!Arr::has($modelData, $field)) {
                    continue;
                }

                $record  = $masterAsset->$field ?? [];
                $changed = false;

                foreach ($modelData[$field] as $currencyCode => $entry) {
                    $value = $entry['value'] ?? null;
                    if ($value === null) {
                        continue;
                    }
                    if (data_get($record, "$currencyCode.independent") === true) {
                        $skipped[$masterAsset->code][] = $currencyCode;
                        continue;
                    }

                    if ($field === 'master_rrps' && $rrpPerUnit) {
                        $value = round((float) $value * (float) $masterAsset->units, 2);
                    }

                    data_set($record, "$currencyCode.value", $value);
                    data_set($record, "$currencyCode.independent", data_get($record, "$currencyCode.independent", false));
                    $changed = true;
                }

                if ($changed) {
                    $dataToUpdate[$field] = $record;
                }
            }

            if ($dataToUpdate) {
                $updatePrices = UpdateMasterAssetPrices::make();
                $updatePrices->forceAsyncCascade = true;
                $updatePrices->action($masterAsset, $dataToUpdate);
                $updated++;
            }
        }

        return [
            'updated' => $updated,
            'skipped' => array_map(fn (array $currencies) => array_values(array_unique($currencies)), $skipped),
        ];
    }

    public function rules(): array
    {
        return [
            'ids'                          => ['required', 'array', 'max:200'],
            'ids.*'                        => ['integer', 'exists:master_assets,id'],
            'rrp_per_unit'                 => ['sometimes', 'boolean'],
            'master_prices'                => ['sometimes', 'array'],
            'master_prices.*.value'        => ['nullable', 'numeric', 'gt:0'],
            'master_prices.*.independent'  => ['sometimes', 'boolean'],
            'master_rrps'                  => ['sometimes', 'array'],
            'master_rrps.*.value'          => ['nullable', 'numeric', 'gt:0'],
            'master_rrps.*.independent'    => ['sometimes', 'boolean'],
        ];
    }

    public function asController(ActionRequest $request): void
    {
        $this->initialisationFromGroup(group(), $request);

        $result = $this->handle(
            $this->validatedData['ids'],
            Arr::except($this->validatedData, ['ids'])
        );

        if ($result['skipped']) {
            $codes = collect($result['skipped'])
                ->map(fn (array $currencies, string $code) => $code.' ('.implode(', ', $currencies).')')
                ->values();

            $request->session()->flash('notification', [
                'status'      => 'warning',
                'title'       => __('Some prices were not changed'),
                'description' => __(':updated updated. Skipped hand-set values: :codes', [
                    'updated' => $result['updated'],
                    'codes'   => $codes->take(10)->implode('; ').($codes->count() > 10 ? ' …' : ''),
                ]),
            ]);
        }
    }

    /**
     * @return array{updated: int, skipped: array<string, list<string>>}
     */
    public function action(array $modelData): array
    {
        $this->asAction = true;
        $this->initialisationFromGroup(group(), $modelData);

        return $this->handle(
            $this->validatedData['ids'],
            Arr::except($this->validatedData, ['ids'])
        );
    }
}
