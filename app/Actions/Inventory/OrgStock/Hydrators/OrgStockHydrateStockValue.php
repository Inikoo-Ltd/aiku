<?php

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;

class OrgStockHydrateStockValue implements ShouldBeUnique
{
    //todo do we need to delete this??? mybe yes
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:org-stock-stock-value {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = OrgStock::class;
    }

    public function getJobUniqueId(OrgStock $orgStock): string
    {
        return $orgStock->id;
    }

    public function handle(OrgStock $orgStock): void
    {
        $tradeUnitIds = DB::table('model_has_trade_units')
            ->where('model_id', $orgStock->id)
            ->where('model_type', 'OrgStock')
            ->pluck('trade_unit_id');

        $stockValue = DB::table('org_stocks')
            ->join('model_has_trade_units', function ($join) {
                $join->on('model_has_trade_units.model_id', '=', 'org_stocks.id')
                    ->where('model_has_trade_units.model_type', '=', 'OrgStock');
            })
            ->whereIn('model_has_trade_units.trade_unit_id', $tradeUnitIds)
            ->sum('org_stocks.quantity_in_locations');

        $orgStock->stats->update([
            'stock_value' => $stockValue ?? 0,
        ]);
    }
}
