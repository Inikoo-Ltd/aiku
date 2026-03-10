<?php

/*
 * author Louis Perez
 * created on 09-03-2026-14h-21m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithMasterAssetTradeUnits;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\ActionRequest;

class RepairMasterAssetTradeUnitsToChildren extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithMastersEditAuthorisation;
    use WithMasterAssetTradeUnits;

    private MasterAsset $masterAsset;

    /**
     * @throws \Throwable
     */
    public function handle(MasterAsset $masterAsset, array $modelData): MasterAsset
    {
        $tradeUnitData = $masterAsset->tradeUnits
            ->map(function ($tradeUnit) {
                $tradeUnit->quantity = $tradeUnit->pivot->quantity;

                return $tradeUnit;
            })->toArray();

        return UpdateMasterAsset::make()->action($masterAsset, [
            'trade_units'   => $tradeUnitData,
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function asController(MasterAsset $masterAsset, ActionRequest $request): MasterAsset
    {
        $this->masterAsset = $masterAsset;
        $this->initialisationFromGroup($masterAsset->group, $request);

        return $this->handle($masterAsset, $this->validatedData);
    }
}
