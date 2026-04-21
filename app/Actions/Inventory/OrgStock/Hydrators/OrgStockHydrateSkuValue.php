<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Apr 2026 12:49:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;

class OrgStockHydrateSkuValue implements ShouldBeUnique
{
    use WithHydrateCommand;
    use CalculatesOrgStockHistories;

    public string $commandSignature = 'org_stocks:sku_value {organisations?*} {--s|slugs=}';

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
        $orgStock->update([
            'sku_value' => $this->getCostPerSku($orgStock, Carbon::now())
        ]);
    }
}
