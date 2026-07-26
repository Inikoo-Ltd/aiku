<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\ActionRequest;

/**
 * Specialised save path for master price/rrp edits: cascades to child products with the
 * per-product cache-break job skipped, then breaks the webpages cache synchronously so
 * the user sees the new prices on the website immediately after saving.
 */
class UpdateMasterAssetPrices extends OrgAction
{
    use WithActionUpdate;
    use WithMastersEditAuthorisation;

    public const int SYNC_CASCADE_MAX_PRODUCTS = 5;

    public function handle(MasterAsset $masterAsset, array $modelData): MasterAsset
    {
        if ($eurPrice = data_get($modelData, 'master_prices.EUR.value')) {
            data_set($modelData, 'price', $eurPrice);
        }
        if ($eurRRP = data_get($modelData, 'master_rrps.EUR.value')) {
            data_set($modelData, 'rrp', $eurRRP);
        }

        $masterAsset = $this->update($masterAsset, $modelData);

        $changedPrices = $masterAsset->wasChanged(['master_prices', 'price']);
        $changedRRPs   = $masterAsset->wasChanged(['master_rrps', 'rrp']);

        if ($changedPrices || $changedRRPs) {
            $type = $changedPrices && $changedRRPs ? 'both' : ($changedPrices ? 'price' : 'rrp');

            if ($masterAsset->products()->count() <= self::SYNC_CASCADE_MAX_PRODUCTS) {
                CascadeMasterAssetPricesToChildren::run($masterAsset, $type);
            } else {
                CascadeMasterAssetPricesToChildren::dispatch($masterAsset, $type);
            }
        }

        return $masterAsset;
    }

    public function rules(): array
    {
        return [
            'master_prices'                => ['sometimes', 'array'],
            'master_prices.*.value'        => ['sometimes', 'numeric', 'gt:0'],
            'master_prices.*.independent'  => ['sometimes', 'boolean'],
            'master_rrps'                  => ['sometimes', 'array'],
            'master_rrps.*.value'          => ['sometimes', 'numeric', 'gt:0'],
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
