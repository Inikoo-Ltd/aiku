<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Dec 2025 10:05:42 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Dec 2025 09:45:00 Central European Time
 */

namespace App\Actions\Maintenance\Dispatching;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithOrganisationSource;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RepairMissingOrgSockSourceId
{
    use WithActionUpdate;
    use WithOrganisationSource;


    public string $commandSignature = 'repair:org_stock_source_id {organisation_id : Organisation id to scope the repair} {--D|dry-run : Only print matched Part SKU without updating}';

    public function handle(OrgStock $orgStock, Command $command, bool $dryRun = false): ?string
    {


        // We match org stock reference (stored as code) with Aurora `Part Dimension`.`Part Reference`
        // and set source_id to "{organisation_id}:{Part SKU}"

        $auroraRow = DB::connection('aurora')
            ->table('Part Dimension')
            ->whereRaw('lower(`Part Reference`) = ?', [strtolower($orgStock->code)])
            ->first();




        if ($auroraRow && isset($auroraRow->{"Part SKU"})) {
            $sku = $auroraRow->{"Part SKU"};


            $command->line("Match with aurora $orgStock->slug: A: ".$auroraRow->{"Part Reference"});

            if(!$dryRun) {
                $orgStock->update([
                    'source_id' => $orgStock->organisation_id.':'.$sku,
                ]);
            }
        }else{
            $command->line("No match with aurora $orgStock->code");
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): void
    {
        $organisationId = (int)$command->argument('organisation_id');
        $dryRun         = (bool)$command->option('dry-run');

        $organisation= Organisation::find($organisationId);
        $organisationSource = $this->getOrganisationSource($organisation);
        $organisationSource->initialisation($organisation);


        $query = OrgStock::where('organisation_id', $organisationId)
            ->whereNull('source_id');






        $query->orderBy('id')
            ->chunk(100, function (Collection $models) use ( $command,$dryRun) {
                foreach ($models as $model) {
                    $this->handle($model, $command, $dryRun);

                }
            });

    }
}
