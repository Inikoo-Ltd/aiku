<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Sep 2026 10:00:00 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Procurement\OrgSupplierProducts\UpdateOrgSupplierProduct;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairAuroraOrgSupplierProductAvailability
{
    use AsAction;

    public string $commandSignature = 'repair:aurora_org_supplier_product_availability {organisations?*} {--N|dry_run}';
    public string $commandDescription = 'Set org supplier product availability from the Aurora supplier part status of its organisation';

    /**
     * @return array{organisation: string, switched_on: int, switched_off: int, missing_in_aurora: int}
     */
    public function handle(Organisation $organisation, bool $dryRun = false): array
    {
        $organisationSource = new AuroraOrganisationService();
        $organisationSource->initialisation($organisation);

        $auroraStatuses = DB::connection('aurora')
            ->table('Supplier Part Dimension')
            ->pluck('Supplier Part Status', 'Supplier Part Key');

        $result = [
            'organisation'      => $organisation->slug,
            'switched_on'       => 0,
            'switched_off'      => 0,
            'missing_in_aurora' => 0,
        ];

        OrgSupplierProduct::where('organisation_id', $organisation->id)
            ->where('source_id', 'like', $organisation->id.':%')
            ->chunkById(500, function ($orgSupplierProducts) use ($auroraStatuses, $dryRun, &$result) {
                foreach ($orgSupplierProducts as $orgSupplierProduct) {
                    $auroraStatus = $auroraStatuses->get(explode(':', $orgSupplierProduct->source_id)[1]);
                    if ($auroraStatus === null) {
                        $result['missing_in_aurora']++;
                        continue;
                    }

                    $isAvailable = $auroraStatus !== 'NoAvailable';
                    if ($isAvailable === $orgSupplierProduct->is_available) {
                        continue;
                    }

                    if (!$dryRun) {
                        UpdateOrgSupplierProduct::run($orgSupplierProduct, ['is_available' => $isAvailable]);
                    }
                    $result[$isAvailable ? 'switched_on' : 'switched_off']++;
                }
            });

        return $result;
    }

    public function asCommand(Command $command): int
    {
        $dryRun = (bool) $command->option('dry_run');

        $query = Organisation::query()
            ->where('type', OrganisationTypeEnum::SHOP->value)
            ->whereNotNull('source');
        if ($command->argument('organisations')) {
            $query->whereIn('slug', $command->argument('organisations'));
        }

        foreach ($query->get() as $organisation) {
            if (!Arr::get($organisation->source, 'db_name')) {
                continue;
            }
            $result = $this->handle($organisation, $dryRun);
            $command->info(sprintf(
                '%s: %d %s on, %d off, %d not in Aurora',
                $result['organisation'],
                $result['switched_on'],
                $dryRun ? 'would be switched' : 'switched',
                $result['switched_off'],
                $result['missing_in_aurora']
            ));
        }

        return 0;
    }
}
