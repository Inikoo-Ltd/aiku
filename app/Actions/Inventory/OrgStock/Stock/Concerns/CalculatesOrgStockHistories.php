<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Apr 2026 15:54:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock\Concerns;

use App\Actions\Inventory\LocationOrgStock\GetLocationOrgStockQuantity;
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

        if ($orgStock->current_supplier_sku_cost) {
            return $orgStock->current_supplier_sku_cost;
        }

        return $orgStock->unit_cost * $orgStock->packed_in;// todo remove this, when removing $orgStock->unit_cost from DB
    }

    public function getWacPerSku(OrgStock $orgStock, Carbon $date): ?float
    {
        return $this->getValuationPerSku($orgStock, $date)['wac'];
    }

    public function getFifoPerSku(OrgStock $orgStock, Carbon $date): ?float
    {
        return $this->getValuationPerSku($orgStock, $date)['fifo'];
    }

    /**
     * @return array{wac: ?float, fifo: ?float}
     */
    public function getValuationPerSku(OrgStock $orgStock, Carbon $date): array
    {
        $wacStartDate = $orgStock->organisation->wac_calculations_start_date;
        if (!$wacStartDate) {
            return ['wac' => null, 'fifo' => null];
        }
        $wacStartDate = Carbon::parse($wacStartDate);
        if ($date->copy()->endOfDay()->lt($wacStartDate)) {
            return ['wac' => null, 'fifo' => null];
        }

        $state = $this->initValuationState($orgStock, $wacStartDate);

        $movements = OrgStockMovement::on('aiku_no_sticky')
            ->select(['type', 'quantity', 'cost_per_sku', 'org_amount', 'date'])
            ->where('org_stock_id', $orgStock->id)
            ->where('date', '>=', $wacStartDate->copy()->startOfDay()->format('Y-m-d H:i:s.u'))
            ->where('date', '<=', $date->copy()->endOfDay()->format('Y-m-d H:i:s.u'))
            ->orderBy('date')
            ->get();

        foreach ($movements as $movement) {
            $this->applyMovementToValuation($state, $movement, $orgStock);
        }

        $wac  = $state['wac'];
        $fifo = $this->fifoPerSkuFromLayers($state['layers']);
        if ($wac === null || $fifo === null) {
            $lastPurchasePrice = $this->getCostPerSku($orgStock, $date);
        }

        return [
            'wac'  => $wac ?? $lastPurchasePrice,
            'fifo' => $fifo ?? $lastPurchasePrice,
        ];
    }

    /**
     * @return array{onHand: float, wac: ?float, layers: array<int, array{0: float, 1: float}>}
     */
    protected function initValuationState(OrgStock $orgStock, Carbon $valuationStartDate): array
    {
        $onHand = (float)OrgStockMovement::on('aiku_no_sticky')
            ->where('org_stock_id', $orgStock->id)
            ->where('date', '<', $valuationStartDate->copy()->startOfDay()->format('Y-m-d H:i:s.u'))
            ->sum('quantity');

        $openingCost = $onHand > 0 ? $this->getCostPerSku($orgStock, $valuationStartDate) : null;

        return [
            'onHand' => $onHand,
            'wac'    => $openingCost,
            'layers' => $openingCost !== null ? [[$onHand, $openingCost]] : [],
        ];
    }

    protected function applyMovementToValuation(array &$state, object $movement, OrgStock $orgStock): void
    {
        $quantity = (float)$movement->quantity;
        if ($movement->type == OrgStockMovementTypeEnum::PURCHASE && $quantity > 0) {
            $cost = $movement->cost_per_sku;
            if (!$cost && $movement->org_amount > 0) {
                $cost = $movement->org_amount / $quantity;
            }
            if ($cost > 0) {
                if ($state['wac'] === null || $state['onHand'] <= 0) {
                    $state['wac'] = (float)$cost;
                } else {
                    $state['wac'] = ($state['onHand'] * $state['wac'] + $quantity * $cost) / ($state['onHand'] + $quantity);
                }
                if ($state['onHand'] <= 0) {
                    $state['layers'] = [];
                }
                $state['layers'][] = [$quantity, (float)$cost];
            }
        } elseif ($quantity > 0) {
            $cost = $this->fifoPerSkuFromLayers($state['layers']) ?? $this->getCostPerSku($orgStock, Carbon::parse($movement->date));
            $state['layers'][] = [$quantity, $cost];
        } elseif ($quantity < 0) {
            $this->consumeFifoLayers($state['layers'], -$quantity);
        }
        $state['onHand'] += $quantity;
        if ($state['onHand'] < 0) {
            $state['layers'] = [];
        }
    }

    protected function consumeFifoLayers(array &$layers, float $quantity): void
    {
        while ($quantity > 0 && $layers) {
            if ($layers[0][0] > $quantity) {
                $layers[0][0] -= $quantity;

                return;
            }
            $quantity -= $layers[0][0];
            array_shift($layers);
        }
    }

    protected function fifoPerSkuFromLayers(array $layers): ?float
    {
        $totalQuantity = 0;
        $totalValue    = 0;
        foreach ($layers as [$layerQuantity, $layerCost]) {
            $totalQuantity += $layerQuantity;
            $totalValue    += $layerQuantity * $layerCost;
        }

        return $totalQuantity > 0 ? $totalValue / $totalQuantity : null;
    }

    public function getStockQuantity(OrgStock $orgStock, Location $location, ?Carbon $date = null): float
    {
        return GetLocationOrgStockQuantity::run($orgStock, $location, $date);
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

    protected function persistOrgStockHistories(OrgStock $orgStock, Carbon $date, array $orgStockLocationData, float $costPerSku, ?Carbon $lastSoldDate, $hydratorDelay = 30, ?float $wacPerSku = null, ?float $fifoPerSku = null): void
    {
        $orgStockQuantity  = 0;
        $orgStockValue     = 0;
        $grpStockValue     = 0;
        $orgStockWacValue  = $wacPerSku === null ? null : 0;
        $grpStockWacValue  = $wacPerSku === null ? null : 0;
        $orgStockFifoValue = $fifoPerSku === null ? null : 0;
        $grpStockFifoValue = $fifoPerSku === null ? null : 0;
        foreach ($orgStockLocationData as $orgStockLocation) {
            if ($orgStockLocation['quantity'] > 0) {
                $orgStockQuantity += $orgStockLocation['quantity'];
                $orgStockValue    += $orgStockLocation['org_stock_value'];
                $grpStockValue    += $orgStockLocation['grp_stock_value'];
                if ($wacPerSku !== null) {
                    $orgStockWacValue += $orgStockLocation['org_stock_wac_value'] ?? 0;
                    $grpStockWacValue += $orgStockLocation['grp_stock_wac_value'] ?? 0;
                }
                if ($fifoPerSku !== null) {
                    $orgStockFifoValue += $orgStockLocation['org_stock_fifo_value'] ?? 0;
                    $grpStockFifoValue += $orgStockLocation['grp_stock_fifo_value'] ?? 0;
                }
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
                'date'     => $date->format('Y-m-d'),
            ],
            [
                'is_week'                           => $date->isFriday(),
                'is_month'                          => $date->isLastOfMonth(),
                'is_year'                           => $date->isEndOfYear(),
                'number_stocks'                     => 0,
                'number_org_stocks_no_stock'        => 0,
                'number_stocks_org_stocks_no_stock' => 0,
                'number_org_stocks'                 => 0,
                'number_out_of_stock_org_stocks'    => 0,
                'number_location_org_stocks'        => 0,
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
                'number_location_org_stocks'     => 0,
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
                'org_stock_wac_value'           => $orgStockWacValue,
                'grp_stock_wac_value'           => $grpStockWacValue,
                'org_stock_fifo_value'          => $orgStockFifoValue,
                'grp_stock_fifo_value'          => $grpStockFifoValue,
                'org_stock_commercial_value'    => 0,
                'grp_stock_commercial_value'    => 0,
                'value_per_sku'                 => $costPerSku,
                'wac_per_sku'                   => $wacPerSku,
                'fifo_per_sku'                  => $fifoPerSku,
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
                'org_stock_wac_value'           => $wacPerSku === null ? null : max(0, $orgStockLocation['org_stock_wac_value'] ?? $quantity * $wacPerSku),
                'grp_stock_wac_value'           => $wacPerSku === null ? null : max(0, $orgStockLocation['grp_stock_wac_value'] ?? $quantity * $wacPerSku),
                'org_stock_fifo_value'          => $fifoPerSku === null ? null : max(0, $orgStockLocation['org_stock_fifo_value'] ?? $quantity * $fifoPerSku),
                'grp_stock_fifo_value'          => $fifoPerSku === null ? null : max(0, $orgStockLocation['grp_stock_fifo_value'] ?? $quantity * $fifoPerSku),
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
