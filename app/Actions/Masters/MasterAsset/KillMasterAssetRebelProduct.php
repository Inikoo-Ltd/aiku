<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Kills a rebel within a scope: clears only the requested not_follow_master_*
 * opt-out(s) so the product follows its master again for that aspect, then runs
 * the matching master→children fix. A kill from the trade units context must not
 * touch a deliberate price setting, and vice versa.
 */
class KillMasterAssetRebelProduct extends OrgAction
{
    use WithMastersEditAuthorisation;
    use WithMasterAssetCascadeDispatch;
    use WithKillMasterAssetRebels;

    public function handle(MasterAsset $masterProduct, Product $product, string $scope = 'all'): MasterAsset
    {
        $killTradeUnits = in_array($scope, ['trade_units', 'all']);
        $killPrices     = in_array($scope, ['prices', 'all']);

        $this->killRebelFlags($product, $killTradeUnits, $killPrices);

        $this->cascadeToChildren($masterProduct, $killTradeUnits, $killPrices);

        return $masterProduct;
    }

    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::in(['trade_units', 'prices', 'all'])],
        ];
    }

    public function asController(MasterAsset $masterAsset, Product $product, ActionRequest $request): MasterAsset
    {
        if ($product->master_product_id !== $masterAsset->id) {
            throw ValidationException::withMessages([
                'product' => __('Product does not belong to this master')
            ]);
        }

        $this->initialisationFromGroup($masterAsset->group, $request);

        return $this->handle($masterAsset, $product, Arr::get($this->validatedData, 'scope', 'all'));
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
