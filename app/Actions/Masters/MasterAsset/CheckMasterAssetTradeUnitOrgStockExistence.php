<?php

/*
 * author Louis Perez
 * created on 05-03-2026-15h-17m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\ActionRequest;

class CheckMasterAssetTradeUnitOrgStockExistence extends OrgAction
{
    public function handle(MasterAsset $masterProduct, array $modelData): bool
    {
        $isValid = true;

        $tradeUnits = TradeUnit::with([
            'stocks.orgStocks:id,stock_id,organisation_id'
        ])
        ->whereIn('id', data_get($modelData, 'trade_units.*.id'))
        ->get();

        $expected = $masterProduct->masterShop->shops->pluck('organisation_id');

        foreach ($tradeUnits as $tradeUnit) {

            $actual = $tradeUnit->stocks
                ->pluck('orgStocks')
                ->flatten()
                ->pluck('organisation_id')
                ->unique();

            $missing = $expected->diff($actual);

            if ($missing->isNotEmpty()) {
                $isValid = false;
            }
        }

        return $isValid;
    }

    public function rules() 
    {
        return [
            'trade_units'   => ['sometimes', 'array', 'nullable'],
        ];
    }

    public function asController(MasterAsset $masterProduct, ActionRequest $request): bool
    {
        $this->initialisationFromGroup($masterProduct->group, $request);

        return $this->handle($this->masterFamily, $this->validatedData);
    }

    public function jsonResponse(array $result)
    {
        return $result;
    }
}
