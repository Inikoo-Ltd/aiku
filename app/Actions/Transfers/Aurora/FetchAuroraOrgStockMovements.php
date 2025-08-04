<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 31 Aug 2024 12:14:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\Inventory\OrgStockMovement\UpdateOrgStockMovement;
use App\Models\Inventory\OrgStockMovement;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraOrgStockMovements extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:stock_movements {organisations?*} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new} ';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?OrgStockMovement
    {
        if ($orgStockMovementData = $organisationSource->fetchOrgStockMovement($organisationSourceId)) {
            if ($orgStockMovement = OrgStockMovement::where('source_id', $orgStockMovementData['orgStockMovement']['source_id'])
                ->first()) {
                $orgStockMovement = UpdateOrgStockMovement::make()->action(
                    $orgStockMovement,
                    modelData: $orgStockMovementData['orgStockMovement'],
                    hydratorsDelay: 10,
                    strict: false
                );
            } else {
                try {
                    $orgStockMovement = StoreOrgStockMovement::make()->action(
                        orgStock: $orgStockMovementData['orgStock'],
                        location: $orgStockMovementData['location'],
                        modelData: $orgStockMovementData['orgStockMovement'],
                        hydratorsDelay: 10,
                        strict: false
                    );
                    $sourceData       = explode(':', $orgStockMovement->source_id);
                    DB::connection('aurora')->table('Inventory Transaction Fact')
                        ->where('Inventory Transaction Key', $sourceData[1])
                        ->update(['aiku_id' => $orgStockMovement->id]);

                    $this->recordNew($organisationSource);
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $orgStockMovementData['orgStockMovement'], 'orgStockMovement', 'store');

                    return null;
                }
            }


            return $orgStockMovement;
        }

        return null;
    }


    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Inventory Transaction Fact')
            ->select('Inventory Transaction Key as source_id')
            ->whereIn('Inventory Transaction Record Type', ['Movement', 'Helper','Info']);
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }
        $query->orderBy('Date');

        return $query;
    }


    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Inventory Transaction Fact')
            ->whereIn('Inventory Transaction Record Type', ['Movement', 'Helper','Info']);
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }
}
