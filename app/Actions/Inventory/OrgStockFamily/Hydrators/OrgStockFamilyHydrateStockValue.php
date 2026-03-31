<?php

namespace App\Actions\Inventory\OrgStockFamily\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrgStockFamily;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;

class OrgStockFamilyHydrateStockValue implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:org-stock-family-stock-value {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = OrgStockFamily::class;
    }

    public function getJobUniqueId(OrgStockFamily $orgStockFamily): string
    {
        return $orgStockFamily->id;
    }

    public function handle(OrgStockFamily $orgStockFamily): void
    {
        $tradeUnitIds = DB::table('model_has_trade_units')
            ->join('org_stocks', function ($join) use ($orgStockFamily) {
                $join->on('model_has_trade_units.model_id', '=', 'org_stocks.id')
                    ->where('model_has_trade_units.model_type', '=', 'OrgStock')
                    ->where('org_stocks.org_stock_family_id', '=', $orgStockFamily->id);
            })
            ->pluck('model_has_trade_units.trade_unit_id');

        $stockValue = DB::table('org_stocks')
            ->join('model_has_trade_units', function ($join) {
                $join->on('model_has_trade_units.model_id', '=', 'org_stocks.id')
                    ->where('model_has_trade_units.model_type', '=', 'OrgStock');
            })
            ->whereIn('model_has_trade_units.trade_unit_id', $tradeUnitIds)
            ->sum('org_stocks.quantity_in_locations');

        $orgStockFamily->stats->update([
            'stock_value' => $stockValue ?? 0,
        ]);
    }
}
