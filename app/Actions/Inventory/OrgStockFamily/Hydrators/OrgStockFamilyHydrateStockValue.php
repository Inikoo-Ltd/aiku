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
        $stockValue = DB::table('org_stocks')
            ->where('org_stocks.org_stock_family_id', $orgStockFamily->id)
            ->whereNull('org_stocks.deleted_at')
            ->sum(DB::raw('coalesce(org_stocks.sku_value, 0) * coalesce(org_stocks.quantity_available, 0)'));

        $orgStockFamily->stats->update([
            'stock_value' => $stockValue ?? 0,
        ]);
    }
}
