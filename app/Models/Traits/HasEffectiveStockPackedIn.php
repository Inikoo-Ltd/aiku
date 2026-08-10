<?php

namespace App\Models\Traits;

use App\Models\Goods\TradeUnit;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
        $stockPackedIn = DB::table('model_has_trade_units')
            ->where('model_type', 'Stock')
            ->whereIn('trade_unit_id', $this->tradeUnits->pluck('id'))
            ->pluck('quantity', 'trade_unit_id')
            ->toArray();

        $orgPackings = $this->getStockPackedInByOrganisationAndTradeUnit();

        return $this->tradeUnits->mapWithKeys(function (TradeUnit $tradeUnit) use ($stockPackedIn, $orgPackings) {
            $packings = ($orgPackings->get($tradeUnit->id) ?? collect())
                ->pluck('packed_in')
                ->map(fn ($packing) => (float) $packing)
                ->unique();

            return [
                $tradeUnit->id => $packings->count() === 1 && $packings->first() > 0
                    ? $packings->first()
                    : (float) Arr::get($stockPackedIn, $tradeUnit->id, 1)
            ];
        })->toArray();
    }

    /**
     * Each organisation's own packing of the trade unit, keyed by trade unit id.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, array{org_stock_id: int, org_code: string, packed_in: float}>>
     */
    public function getStockPackedInByOrganisationAndTradeUnit(): Collection
    {
        return DB::table('model_has_trade_units')
            ->join('org_stocks', 'org_stocks.id', '=', 'model_has_trade_units.model_id')
            ->join('organisations', 'organisations.id', '=', 'org_stocks.organisation_id')
            ->where('model_has_trade_units.model_type', 'OrgStock')
            ->whereIn('model_has_trade_units.trade_unit_id', $this->tradeUnits->pluck('id'))
            ->whereNull('org_stocks.deleted_at')
            ->when($this->organisation_id ?? null, fn ($query, $organisationId) => $query->where('org_stocks.organisation_id', $organisationId))
            ->select([
                'model_has_trade_units.trade_unit_id',
                'org_stocks.id as org_stock_id',
                'organisations.code as org_code',
                'model_has_trade_units.quantity',
            ])
            ->orderBy('organisations.code')
            ->get()
            ->map(fn ($row) => [
                'trade_unit_id' => $row->trade_unit_id,
                'org_stock_id'  => $row->org_stock_id,
                'org_code'      => $row->org_code,
                'packed_in'     => (float) $row->quantity,
            ])
            ->groupBy('trade_unit_id');
    }
}
