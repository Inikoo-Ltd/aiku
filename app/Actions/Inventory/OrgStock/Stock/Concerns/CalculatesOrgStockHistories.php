<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Apr 2026 15:54:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock\Concerns;

use App\Actions\Inventory\OrganisationStockHistory\Hydrators\OrganisationStockHistoryHydrateFromOrgStockHistories;
use App\Models\Inventory\GroupStockHistory;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStockHistory;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockHistory;
use App\Models\Inventory\OrganisationStockHistory;
use App\Models\Inventory\OrgStockMovement;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementClassEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

trait CalculatesOrgStockHistories
{
    public function getCostPerSku(OrgStock $orgStock, Carbon $date): float
    {
        $lastPurchase = OrgStockMovement::on('aiku_no_sticky')->select(['cost_per_sku'])
            ->where('org_stock_id', $orgStock->id)
            ->where('type', OrgStockMovementTypeEnum::PURCHASE->value)
            ->whereNotNull('cost_per_sku')
            ->where('date', '<=', $date->copy()->endOfDay()->format('Y-m-d H:i:s.u'))->orderBy('date', 'desc')->first();

        if ($lastPurchase && $lastPurchase->cost_per_sku > 0) {
            return $lastPurchase->cost_per_sku;
        }

        $closestPurchase = OrgStockMovement::on('aiku_no_sticky')->select(['cost_per_sku'])
            ->where('org_stock_id', $orgStock->id)
            ->where('type', OrgStockMovementTypeEnum::PURCHASE->value)
            ->whereNotNull('cost_per_sku')
            ->where('date', '>', $date->copy()->endOfDay()->format('Y-m-d H:i:s.u'))->orderBy('date')->first();
        if ($closestPurchase && $closestPurchase->cost_per_sku > 0) {
            return $closestPurchase->cost_per_sku;
        }

        return $orgStock->unit_cost * $orgStock->packed_in;
    }

    public function getStockQuantity(OrgStock $orgStock, Location $location, ?Carbon $date=null): float
    {
        if (!$date) {
            $date = now();
        }

        $lastHelper = OrgStockMovement::on('aiku_no_sticky')->select(['audited_quantity', 'date'])
            ->where('org_stock_id', $orgStock->id)
            ->where('location_id', $location->id)
            ->where('class', OrgStockMovementClassEnum::HELPER)
            ->where('date', '<=', $date->copy()->endOfDay()->format('Y-m-d H:i:s.u'))->orderBy('date', 'desc')->first();

        $seedQuantity = $lastHelper?->audited_quantity ?? 0;

        $query = OrgStockMovement::on('aiku_no_sticky')->where('org_stock_id', $orgStock->id)
            ->where('location_id', $location->id)
            ->where('class', OrgStockMovementClassEnum::MOVEMENT)
            ->where('date', '<=', $date->copy()->endOfDay()->format('Y-m-d H:i:s.u'));

        if ($lastHelper) {
            $query->where('date', '>', Carbon::parse($lastHelper->date)->format('Y-m-d H:i:s.u'));
        }

        $sumMovements = $query->sum('quantity');

        return (float)($seedQuantity + $sumMovements);
    }

    public function lastSoldDate(OrgStock $orgStock, Carbon $date): ?Carbon
    {
        $lastSoldData = OrgStockMovement::on('aiku_no_sticky')->select('date')->where('org_stock_id', $orgStock->id)
            ->where('type', OrgStockMovementTypeEnum::PICKED->value)
            ->where('date', '<=', $date->copy()->endOfDay()->format('Y-m-d H:i:s.u'))
            ->orderBy('date', 'desc')->first();
        if ($lastSoldData) {
            return Carbon::parse($lastSoldData->date);
        }

        return null;
    }

    public function nonMovingOneYear(OrgStock $orgStock, Carbon $date, float $quantityOnDate): float
    {
        $firstAssociate = OrgStockMovement::on('aiku_no_sticky')->select('date')->where('org_stock_id', $orgStock->id)
            ->where('type', OrgStockMovementTypeEnum::ASSOCIATE->value)
            ->orderBy('date')->first();

        if ($firstAssociate && Carbon::parse($firstAssociate->date)->lt($date->copy()->subYear())) {
            return 0;
        }

        $sumPickedQuantity = OrgStockMovement::on('aiku_no_sticky')->where('org_stock_id', $orgStock->id)
            ->where('type', OrgStockMovementTypeEnum::PICKED->value)
            ->where('date', '>=', $date->copy()->subYear()->startOfDay()->format('Y-m-d H:i:s.u'))
            ->where('date', '<=', $date->copy()->endOfDay()->format('Y-m-d H:i:s.u'))
            ->sum('quantity');

        $nonMovingQuantity = $quantityOnDate - $sumPickedQuantity;
        if ($nonMovingQuantity < 0) {
            return 0;
        }

        return $nonMovingQuantity;
    }

    protected function wasLocationValid(OrgStock $orgStock, Location $location, Carbon $date, ?Command $command): bool
    {
        $lastMarginalHelper = OrgStockMovement::on('aiku')->select(['type', 'date'])
            ->where('org_stock_id', $orgStock->id)
            ->where('location_id', $location->id)
            ->whereIn('type', [OrgStockMovementTypeEnum::ASSOCIATE, OrgStockMovementTypeEnum::DISASSOCIATE])
            ->where('date', '<=', $date->copy()->endOfDay()->format('Y-m-d H:i:s.u'))->orderBy('date', 'desc')->first();

        if (isset($this->debug) && $this->debug) {
            $command?->warn("{$lastMarginalHelper?->type?->value} ".($lastMarginalHelper ? Carbon::parse($lastMarginalHelper->date)->format('Y-m-d H:i:s.u') : 'N/A'));
        }
        if (!$lastMarginalHelper) {
            if (isset($this->debug) && $this->debug) {
                $command?->error('Location '.$location->slug.' has no stock movements');
            }

            return false;
        }

        if ($lastMarginalHelper->type->value == OrgStockMovementTypeEnum::ASSOCIATE->value) {
            return true;
        }
        $lastHelperDate = Carbon::parse($lastMarginalHelper->date);
        if ($lastHelperDate->lt($date->copy()->startOfDay())) {
            if (isset($this->debug) && $this->debug) {
                $command?->error('Last location is discontinued at '.$lastHelperDate->format('Y-m-d H:i:s.u'));
            }

            return false;
        }

        return true;
    }

    protected function getLocationsIds(OrgStock $orgStock, Carbon $date): array
    {
        return OrgStockMovement::on('aiku_no_sticky')->where('org_stock_id', $orgStock->id)
            ->where('class', OrgStockMovementClassEnum::HELPER)
            ->where('date', '<=', $date->copy()->endOfDay()->format('Y-m-d H:i:s.u'))
            ->distinct('location_id')
            ->pluck('location_id')->toArray();
    }

    protected function persistOrgStockHistories(OrgStock $orgStock, Carbon $date, array $orgStockLocationData, float $costPerSku, ?Carbon $lastSoldDate, $hydratorDelay = 30): void
    {
        $orgStockQuantity = 0;
        $orgStockValue    = 0;
        $grpStockValue    = 0;
        foreach ($orgStockLocationData as $orgStockLocation) {
            if ($orgStockLocation['quantity'] > 0) {
                $orgStockQuantity += $orgStockLocation['quantity'];
                $orgStockValue    += $orgStockLocation['org_stock_value'];
                $grpStockValue    += $orgStockLocation['grp_stock_value'];
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
                OrganisationStockHistoryHydrateFromOrgStockHistories::dispatch($organisationStockHistory->id)->delay($hydratorDelay);
            }
        }

        $nonMovingOneYear = $this->nonMovingOneYear($orgStock, $date, (float)$orgStockQuantity);


        $groupStockHistory = GroupStockHistory::firstOrCreate(
            [
                'group_id' => $orgStock->group_id,
                'date'     => $date->format('Y-m-d')
            ],
            [
                'is_week'  => $date->isFriday(),
                'is_month' => $date->isLastOfMonth(),
                'is_year'  => $date->isEndOfYear(),
            ]
        );


        $organisationStockHistory = OrganisationStockHistory::updateOrCreate(
            [

                'organisation_id' => $orgStock->organisation_id,
                'date'            => $date->format('Y-m-d')
            ],
            [
                'group_id'                       => $orgStock->group_id,
                'group_stock_history_id'         => $groupStockHistory->id,
                'org_stock_value'                => 0,
                'grp_stock_value'                => 0,
                'org_stock_commercial_value'     => 0,
                'grp_stock_commercial_value'     => 0,
                'number_org_stocks'              => 0,
                'number_out_of_stock_org_stocks' => 0,
                'is_week'                        => $date->isFriday(),
                'is_month'                       => $date->isLastOfMonth(),
                'is_year'                        => $date->isEndOfYear(),
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
                'org_stock_value'               => $orgStockValue,
                'grp_stock_value'               => $grpStockValue,
                'org_stock_commercial_value'    => 0,
                'grp_stock_commercial_value'    => 0,
                'value_per_sku'                 => $costPerSku,
                'last_sold_date'                => $lastSoldDate,
                'sold_within_1y'                => $lastSoldDate && $lastSoldDate->gte($date->copy()->subYear()),
                'non_moving_1y'                 => $nonMovingOneYear,
            ]
        );

        foreach ($orgStockLocationData as $orgStockLocation) {
            $quantity      = max(0, $orgStockLocation['quantity']);
            $orgStockValue = max(0, $orgStockLocation['org_stock_value'] ?? $quantity * $costPerSku);
            $grpStockValue = max(0, $orgStockLocation['grp_stock_value'] ?? $quantity * $costPerSku);

            $updateData = [
                'org_stock_history_id'          => $orgStockHistory->id,
                'organisation_stock_history_id' => $orgStockHistory->organisation_stock_history_id,
                'actual_quantity_in_locations'  => $orgStockLocation['quantity'],
                'quantity_in_locations'         => $quantity,
                'org_stock_value'               => $orgStockValue,
                'grp_stock_value'               => $grpStockValue,
            ];

            LocationOrgStockHistory::updateOrCreate(
                [
                    'date'         => $date->format('Y-m-d'),
                    'org_stock_id' => $orgStock->id,
                    'location_id'  => $orgStockLocation['location_id'],
                ],
                $updateData
            );
        }

        OrganisationStockHistoryHydrateFromOrgStockHistories::dispatch($organisationStockHistory->id)->delay(30);
    }
}
