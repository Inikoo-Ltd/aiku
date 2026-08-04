<?php

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateStockValue;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class OrgStockHydrateStockValue implements ShouldBeUnique
{
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
        $stats = $orgStock->stats;

        if (!$stats) {
            return;
        }

        $stats->update([
            'stock_value' => ($orgStock->sku_value ?? 0) * ($orgStock->quantity_available ?? 0),
        ]);

        if ($stats->wasChanged('stock_value') && $orgStock->org_stock_family_id) {
            OrgStockFamilyHydrateStockValue::dispatch($orgStock->orgStockFamily);
        }
    }
}
