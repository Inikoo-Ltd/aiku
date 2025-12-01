<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Dec 2025 11:01:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStock;

use App\Actions\Inventory\OrgStock\AssociateOrgStockToOrgStockFamily;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithOrganisationSource;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairOrgSockWithNoFamily
{
    use WithActionUpdate;
    use WithOrganisationSource;


    public string $commandSignature = 'repair:org_stock_no_family  {--D|dry-run : Only print matched Part SKU without updating}';

    public function handle(OrgStock $orgStock, Command $command, bool $dryRun = false): ?string
    {

        $code = $orgStock->code;

        // Expect codes like "JBB-01"; take the prefix before '-'
        if ($code === '' || !str_contains($code, '-')) {
            // Nothing to do if code doesn't contain '-'
            return null;
        }

        $prefix = explode('-', $code, 2)[0];
        $prefix = trim($prefix);
        if ($prefix === '') {
            return null;
        }

        $orgStockFamily = OrgStockFamily::query()
            ->where('organisation_id', $orgStock->organisation_id)
            ->whereRaw('lower(code) = ?', [strtolower($prefix)])
            ->first();

        if (!$orgStockFamily) {
            $command->line("No family match for $orgStock->slug using prefix '$prefix'");
            return null;
        }

        $command->line("Match family '$orgStockFamily->code' for $orgStock->slug");

        if (!$dryRun) {
            AssociateOrgStockToOrgStockFamily::run($orgStock, $orgStockFamily);

        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): void
    {
        $dryRun         = (bool)$command->option('dry-run');



        $query = OrgStock::whereNull('org_stock_family_id');

        $query->orderBy('id')
            ->chunk(100, function (Collection $models) use ($command, $dryRun) {
                foreach ($models as $model) {
                    $this->handle($model, $command, $dryRun);

                }
            });

    }
}
