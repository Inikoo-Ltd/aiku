<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Mar 2026 18:54:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Actions\Inventory\OrganisationStockHistory\Hydrators\OrganisationStockHistoryHydrateFromOrgStockHistories;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementClassEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\LocationOrgStockHistory;
use App\Models\Inventory\OrgStockHistory;
use App\Models\Inventory\OrganisationStockHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreOrgStockHistoricLocationsStock
{
    use AsAction;

    public bool $debug = false;

    public function handle(OrgStock $orgStock, Carbon $date, ?Command $command = null): array
    {
        $orgStockLocationData = [];
        $locationsIds         = $this->getLocationsIds($orgStock, $date);

        foreach ($locationsIds as $locationId) {

            $location = Location::withTrashed()->find($locationId);
            if ($location) {
                if ($this->debug) {
                    $command?->warn("Checking location $location->slug");
                }
                $wasLocationValid = $this->wasLocationValid($orgStock, $location, $date, $command);
                if ($wasLocationValid) {
                    $quantity = $this->getStockQuantity($orgStock, $location, $date);
                    $command?->line('Stock on '.$location->slug.' ('.$location->id.')  '.$date->format('Y-m-d').'  '.$quantity);
                    $orgStockLocationData[] = [
                        'location_id' => $location->id,
                        'quantity'    => $quantity,
                    ];
                }
            }
        }
        $orgStockQuantity = 0;
        foreach ($orgStockLocationData as $orgStockLocation) {
            if ($orgStockLocation['quantity'] > 0) {
                $orgStockQuantity += $orgStockLocation['quantity'];
            }
        }

        if (empty($orgStockLocationData)) {
            LocationOrgStockHistory::where('date', $date->format('Y-m-d'))
                ->where('org_stock_id', $orgStock->id)
                ->delete();

            OrgStockHistory::where('date', $date->format('Y-m-d'))
                ->where('organisation_id', $orgStock->organisation_id)
                ->where('org_stock_id', $orgStock->id)
                ->delete();

            $organisationStockHistory = OrganisationStockHistory::where('date', $date->format('Y-m-d'))->where('organisation_id', $orgStock->organisation_id)->first();
            if ($organisationStockHistory) {
                OrganisationStockHistoryHydrateFromOrgStockHistories::dispatch($organisationStockHistory->id)->delay(30);
            }
        }


        $organisationStockHistory = OrganisationStockHistory::firstOrCreate(
            [
                'organisation_id' => $orgStock->organisation_id,
                'date'            => $date->format('Y-m-d')
            ],
            [
                'group_id'                       => $orgStock->group_id,
                'org_stock_value'                => 0,
                'grp_stock_value'                => 0,
                'org_stock_commercial_value'     => 0,
                'grp_stock_commercial_value'     => 0,
                'number_org_stocks'              => 0,
                'number_out_of_stock_org_stocks' => 0
            ]
        );


        $orgStockHistory = OrgStockHistory::updateOrCreate(
            [
                'date'            => $date->format('Y-m-d'),
                'organisation_id' => $orgStock->organisation_id,
                'org_stock_id'    => $orgStock->id
            ],
            [
                'organisation_stock_history_id' => $organisationStockHistory->id,
                'quantity_in_locations'         => $orgStockQuantity,
                'number_locations'              => count($orgStockLocationData),
                'org_stock_value'               => 0,
                'grp_stock_value'               => 0,
                'org_stock_commercial_value'    => 0,
                'grp_stock_commercial_value'    => 0,
                'unit_value'                    => 0
            ]
        );

        foreach ($orgStockLocationData as $orgStockLocation) {
            LocationOrgStockHistory::updateOrCreate(
                [
                    'date'         => $date->format('Y-m-d'),
                    'org_stock_id' => $orgStock->id,
                    'location_id'  => $orgStockLocation['location_id']
                ],
                [
                    'org_stock_history_id'         => $orgStockHistory->id,
                    'actual_quantity_in_locations' => $orgStockLocation['quantity'],
                    'quantity_in_locations'        => max(0, $orgStockLocation['quantity'])
                ]
            );
        }

        OrganisationStockHistoryHydrateFromOrgStockHistories::dispatch($organisationStockHistory->id)->delay(30);

        return $orgStockLocationData;
    }

    public function getStockQuantity(OrgStock $orgStock, Location $location, Carbon $date)
    {
        $lastHelper = OrgStockMovement::select(['audited_quantity', 'date'])
            ->where('org_stock_id', $orgStock->id)
            ->where('location_id', $location->id)
            ->where('class', OrgStockMovementClassEnum::HELPER)
            ->where('date', '<=', $date->copy()->endOfDay()->format('Y-m-d H:i:s.u'))->orderBy('date', 'desc')->first();

        $seedQuantity = $lastHelper->audited_quantity;


        $sumMovements = OrgStockMovement::where('org_stock_id', $orgStock->id)
            ->where('location_id', $location->id)
            ->where('class', OrgStockMovementClassEnum::MOVEMENT)
            ->where('date', '>', Carbon::parse($lastHelper->date)->format('Y-m-d H:i:s.u'))
            ->where('date', '<=', $date->copy()->endOfDay()->format('Y-m-d H:i:s.u'))
            ->sum('quantity');

        return $seedQuantity + $sumMovements;
    }

    private function wasLocationValid(OrgStock $orgStock, Location $location, Carbon $date, ?Command $command): bool
    {
        $lastMarginalHelper = OrgStockMovement::select(['type', 'date'])
            ->where('org_stock_id', $orgStock->id)
            ->where('location_id', $location->id)
            ->whereIn('type', [OrgStockMovementTypeEnum::ASSOCIATE, OrgStockMovementTypeEnum::DISASSOCIATE])
            ->where('date', '<=', $date->copy()->endOfDay()->format('Y-m-d H:i:s.u'))->orderBy('date', 'desc')->first();

        if ($this->debug) {
            $command->warn("{$lastMarginalHelper->type->value} ".Carbon::parse($lastMarginalHelper->date)->format('Y-m-d H:i:s.u'));
        }
        if (!$lastMarginalHelper) {
            if ($this->debug) {
                $command?->error('Location '.$location->slug.' has no stock movements');
            }

            return false;
        }

        if ($lastMarginalHelper->type->value == OrgStockMovementTypeEnum::ASSOCIATE->value) {
            return true;
        }
        $lastHelperDate = Carbon::parse($lastMarginalHelper->date);
        if ($lastHelperDate->lt($date->copy()->startOfDay())) {
            if ($this->debug) {
                $command?->error('Last location is discontinued at '.$lastHelperDate->format('Y-m-d H:i:s.u'));
            }

            return false;
        }

        return true;
    }

    private function getLocationsIds(OrgStock $orgStock, Carbon $date): array
    {

        return OrgStockMovement::where('org_stock_id', $orgStock->id)
            ->where('class', OrgStockMovementClassEnum::HELPER)
            ->where('date', '<=', $date->copy()->endOfDay()->format('Y-m-d H:i:s.u'))
            ->distinct('location_id')
            ->pluck('location_id')->toArray();
    }


    public function getCommandSignature(): string
    {
        return 'org_stock:run_quantity_on_locations {orgStock : OrgStock ID or slug} {date?}';
    }

    public function asCommand(Command $command): int
    {
        $this->debug = true;
        if (is_numeric($command->argument('orgStock'))) {
            $orgStock = OrgStock::where('id', $command->argument('orgStock'))->firstOrFail();
        } else {
            $orgStock = OrgStock::where('slug', $command->argument('orgStock'))->firstOrFail();
        }

        if ($command->argument('date')) {
            $date = Carbon::parse($command->argument('date'));
        } else {
            $date = Carbon::now();
        }

        $command->line("Get Stock of $orgStock->slug  ($orgStock->id) on ".$date->format('Y-m-d'));
        $this->handle($orgStock, $date, $command);

        return 0;
    }

}
