<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\MasterAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Kills every rebel of a master within a scope: clears the requested
 * not_follow_master_* opt-outs on its child products, then runs the matching
 * master→children fix. Products are updated one by one rather than by a mass
 * query so each flag change leaves its own audit record.
 */
class KillAllMasterAssetRebelProducts extends OrgAction
{
    use WithMastersEditAuthorisation;
    use WithMasterAssetCascadeDispatch;
    use WithKillMasterAssetRebels;

    public function handle(MasterAsset $masterProduct, string $scope = 'all'): MasterAsset
    {
        $killTradeUnits = in_array($scope, ['trade_units', 'all']);
        $killPrices     = in_array($scope, ['prices', 'all']);

        $rebels = $masterProduct->products()
            ->where(function ($query) use ($killTradeUnits, $killPrices) {
                $query->when($killTradeUnits, fn ($q) => $q->orWhere('not_follow_master_trade_units', true))
                    ->when($killPrices, fn ($q) => $q->orWhere('not_follow_master_prices', true));
            })
            ->get();

        foreach ($rebels as $rebel) {
            $this->killRebelFlags($rebel, $killTradeUnits, $killPrices);
        }

        $this->cascadeToChildren($masterProduct, $killTradeUnits, $killPrices);

        return $masterProduct;
    }

    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::in(['trade_units', 'prices', 'all'])],
        ];
    }

    public function asController(MasterAsset $masterAsset, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup($masterAsset->group, $request);

        return $this->handle($masterAsset, Arr::get($this->validatedData, 'scope', 'all'));
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
