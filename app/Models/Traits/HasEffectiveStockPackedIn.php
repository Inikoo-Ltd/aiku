<?php

namespace App\Models\Traits;

use App\Models\Goods\TradeUnit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait HasEffectiveStockPackedIn
{
    /**
     * The warehouses' own packing wins over the group stock's when they all agree:
     * a product of 4 units packed in 4s picks 1 SKO, whatever the group stock says.
     * On models scoped to one organisation only that organisation's packing counts.
     *
     * @return array<int, float>
     */
    public function getEffectiveStockPackedInByTradeUnit(): array
    {
        $tradeUnitIds = $this->tradeUnits->pluck('id');

        $stockPackedIn = DB::table('model_has_trade_units')
            ->where('model_type', 'Stock')
            ->whereIn('trade_unit_id', $tradeUnitIds)
            ->pluck('quantity', 'trade_unit_id')
            ->toArray();

        $orgPackings = DB::table('model_has_trade_units')
            ->join('org_stocks', 'org_stocks.id', '=', 'model_has_trade_units.model_id')
            ->where('model_has_trade_units.model_type', 'OrgStock')
            ->whereIn('model_has_trade_units.trade_unit_id', $tradeUnitIds)
            ->whereNull('org_stocks.deleted_at')
            ->when($this->organisation_id ?? null, fn ($query, $organisationId) => $query->where('org_stocks.organisation_id', $organisationId))
            ->select('model_has_trade_units.trade_unit_id', 'model_has_trade_units.quantity')
            ->get()
            ->groupBy('trade_unit_id');

        return $this->tradeUnits->mapWithKeys(function (TradeUnit $tradeUnit) use ($stockPackedIn, $orgPackings) {
            $packings = ($orgPackings->get($tradeUnit->id) ?? collect())
                ->pluck('quantity')
                ->map(fn ($packing) => (float) $packing)
                ->unique();

            return [
                $tradeUnit->id => $packings->count() === 1 && $packings->first() > 0
                    ? $packings->first()
                    : (float) Arr::get($stockPackedIn, $tradeUnit->id, 1)
            ];
        })->toArray();
    }
}
