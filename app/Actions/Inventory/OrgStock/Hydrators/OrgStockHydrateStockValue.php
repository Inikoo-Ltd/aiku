<?php

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;

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
        $stockValue = ($orgStock->sku_value ?? 0) * ($orgStock->quantity_available ?? 0);

        $orgStock->stats->update([
            'stock_value' => $stockValue,
        ]);
    }
}
