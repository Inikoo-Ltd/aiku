<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 18 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnused */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Models\SysAdmin\Organisation;
use App\Transfers\Aurora\FetchAuroraLocation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairNotFoundAuroraLocations
{
    use AsAction;
    use WithOrganisationSource;

    public string $commandSignature = 'repair:not_found_aurora_locations {organisations?*} {--D|dry-run}';

    public function handle(Organisation $organisation, Command $command, bool $dryRun): void
    {
        $this->getOrganisationSource($organisation)->initialisation($organisation);

        $placeholders = Location::withTrashed()
            ->where('organisation_id', $organisation->id)
            ->where('code', 'like', 'not_found_aiku_'.$organisation->id.'_%')
            ->whereExists(fn ($query) => $query->select(DB::raw(1))->from('org_stock_movements')->whereColumn('org_stock_movements.location_id', 'locations.id'))
            ->get();

        foreach ($placeholders as $placeholder) {
            $auroraLocationKey  = (int)substr($placeholder->code, strlen('not_found_aiku_'.$organisation->id.'_'));
            $auroraLocationData = DB::connection('aurora')->table('Location Dimension')->where('Location Key', $auroraLocationKey)->first();
            if (!$auroraLocationData) {
                continue;
            }

            $movements = DB::table('org_stock_movements')->where('location_id', $placeholder->id);

            if (!$this->hasStockStillInLocation($placeholder)) {
                continue;
            }

            $command->line(sprintf(
                '%s: aurora location %s (%s) %d movements, %d org stocks',
                $organisation->slug,
                $auroraLocationKey,
                $auroraLocationData->{'Location Code'},
                (clone $movements)->count(),
                (clone $movements)->distinct()->count('org_stock_id')
            ));

            if ($dryRun) {
                continue;
            }

            $location = $this->getAikuLocation($organisation, $auroraLocationData);
            if (!$location) {
                $command->error("Could not get aiku location for aurora location $auroraLocationKey");
                continue;
            }
            $command->info("  -> $location->code ($location->id)");

            $firstMovementDates = (clone $movements)->groupBy('org_stock_id')->selectRaw('org_stock_id, min(date) as first_date')->pluck('first_date', 'org_stock_id');

            DB::transaction(function () use ($placeholder, $location, $firstMovementDates) {
                DB::table('org_stock_movements')->where('location_id', $placeholder->id)->update(['location_id' => $location->id]);
                DB::table('org_stock_audit_deltas')->where('location_id', $placeholder->id)->update(['location_id' => $location->id]);

                foreach ($firstMovementDates as $orgStockId => $firstMovementDate) {
                    $orgStock = OrgStock::find($orgStockId);
                    if (!$orgStock->locationOrgStocks()->where('location_id', $location->id)->exists()) {
                        StoreLocationOrgStock::make()->action($orgStock, $location, [
                            'date' => Carbon::parse($firstMovementDate)->subMilliseconds(50)->format('Y-m-d H:i:s.u'),
                        ]);
                    }
                }
            });

            foreach ($firstMovementDates->keys() as $orgStockId) {
                RepairLocationOrgStockPurchasesPostMigration::run($orgStockId, $command);
            }
        }
    }

    private function hasStockStillInLocation(Location $placeholder): bool
    {
        $lastAssociationTypes = DB::table('org_stock_movements')
            ->selectRaw('DISTINCT ON (org_stock_id) type')
            ->where('location_id', $placeholder->id)
            ->whereIn('type', [OrgStockMovementTypeEnum::ASSOCIATE->value, OrgStockMovementTypeEnum::DISASSOCIATE->value])
            ->orderBy('org_stock_id')
            ->orderByDesc('date')
            ->orderByDesc('id');

        $stocksOnPlaceholder = DB::table('org_stock_movements')->where('location_id', $placeholder->id)->distinct()->count('org_stock_id');
        $stocksTakenOff      = DB::query()->fromSub($lastAssociationTypes, 'last_association')->where('type', OrgStockMovementTypeEnum::DISASSOCIATE->value)->count();

        return $stocksTakenOff < $stocksOnPlaceholder;
    }

    private function getAikuLocation(Organisation $organisation, object $auroraLocationData): ?Location
    {
        $sourceId = $organisation->id.':'.$auroraLocationData->{'Location Key'};

        $location = Location::where('source_id', $sourceId)->first()
            ?? FetchAuroraLocation::findAikuLocation($organisation->id, $auroraLocationData);
        if ($location) {
            return $location;
        }

        $parent = WarehouseArea::where('source_id', $organisation->id.':'.$auroraLocationData->{'Location Warehouse Area Key'})->first()
            ?? Warehouse::where('source_id', $organisation->id.':'.$auroraLocationData->{'Location Warehouse Key'})->first();
        if (!$parent) {
            return null;
        }

        return StoreLocation::make()->action($parent, [
            'code'      => FetchAuroraLocation::normaliseCode($auroraLocationData->{'Location Code'}),
            'source_id' => $sourceId,
        ], strict: false);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $organisations = Organisation::where('is_aiku_stock_control', true)->whereNotNull('source');
        if ($command->argument('organisations')) {
            $organisations->whereIn('slug', $command->argument('organisations'));
        }

        foreach ($organisations->get() as $organisation) {
            $this->handle($organisation, $command, (bool)$command->option('dry-run'));
        }

        return 0;
    }
}
